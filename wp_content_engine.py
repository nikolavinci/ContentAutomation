#!/usr/bin/env python3
"""
WordPress Content Automation Engine
Generates, validates, and publishes content to WordPress sites automatically.

Usage:
    python wp_content_engine.py --config config.yaml --dry-run
    python wp_content_engine.py --config config.yaml --publish
"""

import os
import json
import logging
import argparse
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()
from typing import Dict, List, Optional
import openai
import requests
import feedparser
from bs4 import BeautifulSoup
from dataclasses import dataclass, field
from duckduckgo_search import DDGS

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('content_automation.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)


@dataclass
class ContentConfig:
    """Configuration for a WordPress site"""
    site_url: str
    rest_api_url: str
    username: str
    password: str  # Use app-specific password for security
    category_id: int
    tags: List[str]
    niche: str  # e.g., "tech startups", "sustainable business"
    authors: List[str]  # Byline names
    provider: str = "openai"  # "openai" or "gemini"
    rss_feeds: List[str] = field(default_factory=list)
    min_word_count: int = 800
    min_grade: int = 8
    max_grade: int = 12


@dataclass
class Article:
    """Generated article with metadata"""
    title: str
    content: str
    excerpt: str
    featured_image_url: Optional[str]
    author_name: str
    tags: List[str]
    category_id: int
    plagiarism_score: float
    readability_grade: float
    word_count: int
    seo_score: float
    metadata: Dict



class ContentGenerator:
    """Generates article content using OpenAI or Gemini API with fallback"""
    
    def __init__(self, provider: str = "openai", api_key: str = None):
        self.primary_provider = provider
        self.clients = {}
        self.models = {"openai": "gpt-4o", "gemini": "gemini-3.7-flash"}
        
        openai_key = api_key if provider == "openai" and api_key else os.getenv("OPENAI_API_KEY")
        if openai_key:
            self.clients["openai"] = openai.OpenAI(api_key=openai_key)
            
        gemini_key = api_key if provider == "gemini" and api_key else os.getenv("GEMINI_API_KEY")
        if gemini_key:
            from google import genai
            self.clients["gemini"] = genai.Client(api_key=gemini_key)
            
        if not self.clients:
            logger.warning("No API keys found for OpenAI or Gemini.")
            
    def _call_llm(self, prompt: str, provider: str) -> (str, dict):
        if provider not in self.clients:
            raise ValueError(f"Provider {provider} is not configured (missing API key).")
            
        if provider == "openai":
            resp = self.clients["openai"].chat.completions.create(
                model=self.models["openai"],
                messages=[{"role": "user", "content": prompt}]
            )
            content = resp.choices[0].message.content
            usage = {"prompt_tokens": resp.usage.prompt_tokens, "completion_tokens": resp.usage.completion_tokens}
            return content, usage
        elif provider == "gemini":
            resp = self.clients["gemini"].models.generate_content(
                model=self.models["gemini"],
                contents=prompt
            )
            content = resp.text
            # Gemini Python SDK doesn't always expose usage easily, return 0 for now
            usage = {"prompt_tokens": 0, "completion_tokens": 0}
            return content, usage
            
    def generate_with_fallback(self, prompt: str) -> (str, str, dict):
        """Attempts generation with primary provider, falls back to alternatives if it fails."""
        providers_to_try = [self.primary_provider]
        for p in self.clients.keys():
            if p != self.primary_provider:
                providers_to_try.append(p)
                
        errors = []
        for provider in providers_to_try:
            try:
                logger.info(f"Attempting generation with {provider}...")
                content, usage = self._call_llm(prompt, provider)
                logger.info(f"Successfully generated with {provider}.")
                return content, provider, usage
            except Exception as e:
                logger.warning(f"Provider {provider} failed: {e}")
                errors.append(str(e))
                
        raise Exception(f"All providers failed. Errors: {'; '.join(errors)}")
        
    def generate_featured_image(self, title: str) -> str:
        if "openai" not in self.clients:
            return None
        logger.info(f"Generating featured image for: {title}")
        try:
            resp = self.clients["openai"].images.generate(
                model="dall-e-3",
                prompt=f"A modern, high-quality, professional editorial illustration representing the news headline: '{title}'. The style should be corporate, clean, and engaging. No text in the image.",
                size="1024x1024",
                quality="standard",
                n=1,
            )
            return resp.data[0].url
        except Exception as e:
            logger.warning(f"Failed to generate featured image: {e}")
            return None
        
    def generate_article(self, config: ContentConfig, keyword_angle: str, related_posts: List[Dict], source_text: str = "") -> str:
        """Generate a complete article tailored to the niche"""
        
        internal_links_str = "\\n".join([f"- [{p['title']}]({p['url']})" for p in related_posts]) if related_posts else "None"
        prompt = f"""You are a professional business journalist writing for an international news platform covering {config.niche}.

The specific topic/angle for this article is: {keyword_angle}

You must synthesize the following source material to write a comprehensive, original news article. Cross-check facts and cite the sources naturally.

SOURCE MATERIAL:
{source_text[:15000]}

You must include natural internal links to the following related articles on our site:
{internal_links_str}

Format the article strictly using the following structure:

---
HEADLINE: <The main headline>
SUBTITLE: <A compelling subtitle>
META_DESCRIPTION: <150-160 character SEO meta description>
FOCUS_KEYWORDS: <Comma separated list of focus keywords>
CATEGORIES: <Comma separated list of 3 relevant categories>
TAGS: <Comma separated list of relevant tags>
---

# <The main headline>
*<The subtitle>*

<Start the article here>

Write a comprehensive, engaging article using professional formatting:
- Use ## H2 and ### H3 subheadings to structure the content.
- Write well-paced paragraphs.
- Use bullet points or numbered lists where appropriate for readability.
- Incorporate the internal links naturally into the text.
- Conclude with a solid summary or forward-looking statement.
Ensure the tone is objective and analytical."""
        
        try:
            content, used_provider, usage_stats = self.generate_with_fallback(prompt)
            # You can log usage_stats here if needed
            return content
        except Exception as e:
            logger.error(f"Article generation failed: {e}")
            raise e
class NewsAggregator:
    def __init__(self, provider: str = "openai", api_key: str = None):
        self.generator = ContentGenerator(provider=provider, api_key=api_key)
        
    def _scrape_text(self, url: str) -> str:
        try:
            resp = requests.get(url, timeout=10)
            soup = BeautifulSoup(resp.text, 'html.parser')
            for tag in soup(['script', 'style', 'nav', 'header', 'footer']):
                tag.decompose()
            text = soup.get_text(separator=' ', strip=True)
            return text[:3000] # Limit to 3k chars per source
        except:
            return ""

    def gather_source_material(self, config: ContentConfig) -> (str, str):
        # 1. Parse RSS feeds if available
        rss_stories = []
        if config.rss_feeds:
            for feed_url in config.rss_feeds[:3]:  # Max 3 feeds to avoid context limits
                try:
                    feed = feedparser.parse(feed_url)
                    for entry in feed.entries:
                        if entry.link not in self.crawled_urls:
                            rss_stories.append({"title": entry.title, "link": entry.link})
                            self._mark_crawled(entry.link)
                            break  # Only take 1 new story per feed
                
                except Exception as e:
                    logger.error(f"Failed to parse RSS {feed_url}: {e}")
                    
        # 2. Get recent news from DuckDuckGo
        query = rss_stories[0]["title"] if rss_stories else config.niche
        logger.info(f"Gathering news for topic: {query}")
        
        sources_text = ""
        try:
            # DDGS news search
            from duckduckgo_search import DDGS
            news_results = DDGS().news(query, max_results=3)
            
            for res in news_results:
                url = res.get('url')
                title = res.get('title')
                body = self._scrape_text(url)
                if body:
                    sources_text += f"\\n--- Source: {title} ({url}) ---\\n{body}\\n"
                    
        except Exception as e:
            logger.error(f"News gathering failed: {e}")
            
        for story in rss_stories:
            if story.get("link"):
                body = self._scrape_text(story["link"])
                if body:
                    sources_text = f"\\n--- Primary Source: {story['title']} ({story['link']}) ---\\n{body}\\n" + sources_text
                
        # LLM Angle Selection
        prompt = f"Based on the following recent news sources, suggest ONE highly specific, compelling headline/angle for a new comprehensive article.\\n\\nSources:\\n{sources_text[:5000]}"
        try:
            if self.generator.provider == "openai":
                resp = self.generator.client.chat.completions.create(model=self.generator.model, messages=[{"role": "user", "content": prompt}])
                angle = resp.choices[0].message.content.strip(' "')
            else:
                resp = self.generator.client.models.generate_content(model=self.generator.model, contents=prompt)
                angle = resp.text.strip(' "')
        except:
            angle = query
            
        return angle, sources_text

class TelegramNotifier:
    def __init__(self):
        import os
        import requests
        self.bot_token = os.getenv("TELEGRAM_BOT_TOKEN")
        self.chat_id = os.getenv("TELEGRAM_CHAT_ID")
        
    def send_draft_alert(self, title: str, post_id: int, url: str, cost: float = 0.0):
        if not self.bot_token or not self.chat_id:
            logger.warning("Telegram credentials missing, skipping alert.")
            return
            
        import requests
        text = f"📝 *New Draft Ready*\n\n*Title:* {title}\n*Cost:* ${cost:.4f}\n*Link:* {url}\n\nReply with `Approve {post_id}` to publish it live."
        endpoint = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
        try:
            requests.post(endpoint, json={"chat_id": self.chat_id, "text": text, "parse_mode": "Markdown"})
        except Exception as e:
            logger.error(f"Failed to send Telegram alert: {e}")

class QualityAssurance:
    """Validates article quality before publishing"""
    
    def __init__(self):
        self.copyscape_api_key = os.getenv("COPYSCAPE_API_KEY")
    
    def check_plagiarism(self, content: str, url: Optional[str] = None) -> Dict:
        """
        Check plagiarism using Copyscape API
        Returns: {score: 0-100, flagged_sources: [...], status: 'pass'|'fail'}
        """
        if not self.copyscape_api_key:
            logger.warning("COPYSCAPE_API_KEY not set; skipping plagiarism check")
            return {"score": 0, "flagged_sources": [], "status": "skipped"}
        
        # Copyscape API call
        params = {
            "u": self.copyscape_api_key,
            "o": "csearch",
            "t": content[:5000],  # API takes first 5000 chars
            "f": "json"
        }
        
        try:
            resp = requests.post("https://www.copyscape.com/api/", data=params, timeout=30)
            resp.raise_for_status()
            result = resp.json()
            
            # Parse Copyscape response
            matches = result.get("matches", [])
            score = min(100, len(matches) * 10)  # Rough scoring: each match = +10%
            
            if score > 20:
                logger.warning(f"Plagiarism risk detected: {score}%")
                return {
                    "score": score,
                    "flagged_sources": [m.get("url") for m in matches[:5]],
                    "status": "fail"
                }
            
            return {
                "score": score,
                "flagged_sources": [],
                "status": "pass"
            }
        
        except Exception as e:
            logger.error(f"Plagiarism check failed: {e}")
            return {"score": 0, "flagged_sources": [], "status": "error"}
    
    def calculate_readability(self, content: str) -> Dict:
        """
        Calculate Flesch-Kincaid readability grade
        Returns: {grade: float, status: 'pass'|'fail', explanation: str}
        """
        # Remove HTML tags
        import re
        text = re.sub(r'<[^>]+>', '', content)
        
        # Rough Flesch-Kincaid calculation
        sentences = len([s for s in text.split('.') if len(s.strip()) > 10])
        words = len(text.split())
        syllables = self._count_syllables(text)
        
        if sentences == 0 or words == 0:
            return {"grade": 0, "status": "error"}
        
        grade = (0.39 * words / sentences) + (11.8 * syllables / words) - 15.59
        grade = max(0, min(18, grade))  # Clamp to 0-18
        
        status = "pass" if self.config.min_grade <= grade <= self.config.max_grade else "fail"
        
        return {
            "grade": round(grade, 1),
            "status": status,
            "explanation": f"Grade {grade:.1f} (Target {self.config.min_grade}-{self.config.max_grade})"
        }
    
    def _count_syllables(self, text: str) -> int:
        """Rough syllable counter"""
        vowels = "aeiouy"
        syllable_count = 0
        previous_was_vowel = False
        
        for char in text.lower():
            is_vowel = char in vowels
            if is_vowel and not previous_was_vowel:
                syllable_count += 1
            previous_was_vowel = is_vowel
        
        return max(1, syllable_count)
    
    def validate_article(self, article: Article) -> Dict:
        """Run all QA checks"""
        results = {
            "passed": True,
            "checks": {},
            "timestamp": datetime.now().isoformat()
        }
        
        # Word count check
        if article.word_count < self.config.min_word_count:
            results["checks"]["word_count"] = {"status": "fail", "message": f"Only {article.word_count} words; target {self.config.min_word_count}+"}
            results["passed"] = False
        else:
            results["checks"]["word_count"] = {"status": "pass"}
        
        # Plagiarism check
        plagiarism = self.check_plagiarism(article.content)
        if plagiarism["status"] == "fail":
            results["checks"]["plagiarism"] = {"status": "fail", "details": plagiarism}
            results["passed"] = False
        else:
            results["checks"]["plagiarism"] = {"status": "pass"}
        
        # Readability check
        readability = self.calculate_readability(article.content)
        if readability["status"] == "fail":
            results["checks"]["readability"] = {"status": "warn", "details": readability}
        else:
            results["checks"]["readability"] = {"status": "pass", "details": readability}
        
        return results


class WordPressPublisher:
    """Publishes articles to WordPress via REST API"""
    
    def __init__(self, config: ContentConfig):
        self.config = config
        self.session = requests.Session()
        self.session.auth = (config.username, config.password)
    

    def get_related_posts(self, keyword: str) -> List[Dict]:
        if not self.config.rest_api_url or self.config.rest_api_url == "N/A":
            return []
        try:
            endpoint = f"{self.config.rest_api_url}/posts?search={requests.utils.quote(keyword)}&per_page=3"
            resp = self.session.get(endpoint, timeout=10)
            if resp.status_code == 200:
                posts = resp.json()
                return [{"title": p["title"]["rendered"], "url": p["link"]} for p in posts]
        except Exception as e:
            logger.error(f"Failed to fetch related posts: {e}")
        return []
        
    def publish(self, article: Article, draft_only: bool = False) -> Dict:
        """
        Publish article to WordPress
        If draft_only=True, creates as draft (for review)
        """
        
        if not self.config.rest_api_url or self.config.rest_api_url == "N/A":
            return {
                "success": True,
                "url": "N/A (No REST API URL provided)",
                "post_id": 0
            }
        
        payload = {
            "title": article.title,
            "content": article.content,
            "excerpt": article.excerpt,
            "categories": [self.config.category_id],
            "tags": article.tags,
            "status": "draft" if draft_only else "publish",
            "meta": {
                "author_name": article.author_name,
                "plagiarism_score": article.plagiarism_score,
                "readability_grade": article.readability_grade
            }
        }
        
        if article.featured_image_url:
            # Download and attach featured image
            image_id = self._upload_image(article.featured_image_url)
            if image_id:
                payload["featured_media"] = image_id
        
        try:
            endpoint = f"{self.config.rest_api_url}/posts"
            resp = requests.post(endpoint, json=payload, auth=self.session.auth, timeout=30)
            resp.raise_for_status()
            
            post_data = resp.json()
            logger.info(f"Published post ID {post_data['id']}: {article.title}")
            
            return {
                "success": True,
                "post_id": post_data["id"],
                "url": post_data["link"],
                "timestamp": datetime.now().isoformat()
            }
        
        except requests.exceptions.RequestException as e:
            logger.error(f"Failed to publish: {e}")
            return {
                "success": False,
                "error": str(e),
                "timestamp": datetime.now().isoformat()
            }
    
    def _upload_image(self, image_url: str) -> Optional[int]:
        """Download image and upload to WordPress media library"""
        try:
            # Download image
            img_resp = requests.get(image_url, timeout=10)
            img_resp.raise_for_status()
            
            # Upload to WordPress
            files = {"file": ("featured.jpg", img_resp.content, "image/jpeg")}
            endpoint = f"{self.config.rest_api_url}/media"
            resp = requests.post(endpoint, files=files, auth=self.session.auth, timeout=30)
            resp.raise_for_status()
            
            return resp.json()["id"]
        except Exception as e:
            logger.warning(f"Image upload failed: {e}")
            return None


class ContentAutomationEngine:
    """Orchestrates the entire pipeline"""
    
    def __init__(self, config: ContentConfig):
        self.config = config
        self.generator = ContentGenerator(provider=config.provider)
        self.news = NewsAggregator(provider=config.provider)
        self.qa = QualityAssurance(self.config)
        self.publisher = WordPressPublisher(config)
        self.notifier = TelegramNotifier()
    
    def run(self, dry_run: bool = True, draft_only: bool = True) -> Dict:
        """
        Execute full pipeline: generate → validate → publish
        
        dry_run: If True, don't actually publish
        draft_only: If True, save as draft for manual review
        """
        
        logger.info(f"Starting content automation for {self.config.site_url}")
        result = {
            "status": "running",
            "stages": {},
            "timestamp": datetime.now().isoformat()
        }
        
        # Stage 0: News Aggregation & Internal Linking
        keyword_angle, source_text = self.news.gather_source_material(self.config)
        logger.info(f"News Angle: {keyword_angle}")
        related_posts = self.publisher.get_related_posts(keyword_angle)
        
        # Stage 1: Generate
        logger.info("Stage 1: Generating content...")
        try:
            raw_content = self.generator.generate_article(self.config, keyword_angle, related_posts, source_text)
            result["stages"]["generation"] = {"status": "success"}
            logger.info(f"Generated {len(raw_content)} characters")
        except Exception as e:
            result["stages"]["generation"] = {"status": "failed", "error": str(e)}
            result["status"] = "failed"
            return result
        
        # Parse generated content (simplified)
        article = self._parse_generated_content(raw_content)
        
        # Generate featured image
        image_url = self.generator.generate_featured_image(article.title)
        if image_url:
            article.featured_image_url = image_url
            logger.info("Featured image generated successfully.")
        
        # Save local copy
        try:
            import os
            os.makedirs("drafts", exist_ok=True)
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            safe_title = "".join(c for c in article.title if c.isalnum() or c in " -_").strip()
            filename = f"drafts/{timestamp}_{safe_title}.md"
            with open(filename, "w", encoding="utf-8") as f:
                img_md = f"![Featured Image]({article.featured_image_url})\n\n" if article.featured_image_url else ""
                f.write(f"# {article.title}\n\n{img_md}{article.content}")
            logger.info(f"Saved local draft to {filename}")
        except Exception as e:
            logger.warning(f"Could not save local draft: {e}")
        
        # Stage 2: Validate
        logger.info("Stage 2: Validating quality...")
        qa_result = self.qa.validate_article(article)
        result["stages"]["validation"] = qa_result
        
        if not qa_result["passed"]:
            logger.warning("Article failed QA checks")
            result["status"] = "failed_qa"
            return result
        
        # Stage 3: Publish
        logger.info(f"Stage 3: Publishing (dry_run={dry_run})...")
        if dry_run:
            result["stages"]["publish"] = {
                "status": "dry_run",
                "would_publish_to": self.config.site_url,
                "title": article.title
            }
            logger.info("DRY RUN: Would publish the following:")
            logger.info(f"  Title: {article.title}")
            logger.info(f"  Word count: {article.word_count}")
            logger.info(f"  Status: {'draft' if draft_only else 'published'}")
        else:
            pub_result = self.publisher.publish(article, draft_only=draft_only)
            result["stages"]["publish"] = pub_result
            result["status"] = "success" if pub_result["success"] else "publish_failed"
            if pub_result["success"] and draft_only:
                # Estimate cost to show in telegram
                cost = 0.0
                if self.config.provider == "openai":
                    # Rough estimate
                    cost = (article.word_count / 1000) * 0.01 
                self.notifier.send_draft_alert(article.title, pub_result["post_id"], pub_result["url"], cost)
        
        return result
    
    def _parse_generated_content(self, raw_content: str) -> Article:
        """Parse Claude-generated content into Article object"""
        lines = raw_content.split('\n')
        metadata = {}
        content_start = 0
        
        # Parse metadata section
        in_metadata = False
        for i, line in enumerate(lines):
            if line.strip().startswith('---'):
                in_metadata = not in_metadata
                if not in_metadata:
                    content_start = i + 1
                    break
            elif in_metadata and ':' in line:
                key, value = line.split(':', 1)
                metadata[key.strip()] = value.strip()
        
        content = '\n'.join(lines[content_start:])
        word_count = len(content.split())
        
        
        # Include SEO suggestions as bold text at the end of the draft if it's a draft
        seo_footer = f"\n\n---\n### SEO Suggestions\n**Meta Description:** {metadata.get('META_DESCRIPTION', 'N/A')}\n**Focus Keywords:** {metadata.get('FOCUS_KEYWORDS', 'N/A')}\n**Categories:** {metadata.get('CATEGORIES', 'N/A')}\n**Tags:** {metadata.get('TAGS', 'N/A')}\n"
        content = content + seo_footer
        
        return Article(
            title=metadata.get("HEADLINE", "Untitled"),
            content=content,
            excerpt=metadata.get("META_DESCRIPTION", "")[:160],
            featured_image_url=None,
            author_name=metadata.get("AUTHOR", self.config.authors[0]),
            tags=[t.strip() for t in metadata.get("TAGS", "").split(",")] if metadata.get("TAGS") else [],
            category_id=self.config.category_id,
            plagiarism_score=0,
            readability_grade=0,
            word_count=word_count,
            seo_score=0,
            metadata=metadata
        )


def main():
    parser = argparse.ArgumentParser(description="WordPress Content Automation")
    parser.add_argument("--site-url", required=True, help="WordPress site URL")
    parser.add_argument("--rest-api", required=True, help="REST API endpoint")
    parser.add_argument("--username", required=True, help="WP username")
    parser.add_argument("--password", required=True, help="WP app password")
    parser.add_argument("--category-id", type=int, default=1, help="Category ID")
    parser.add_argument("--niche", required=True, help="Content niche")
    parser.add_argument("--authors", nargs="+", required=True, help="Author names")
    parser.add_argument("--provider", choices=["openai", "gemini"], default="openai", help="AI provider to use")
    parser.add_argument("--rss", nargs="*", default=[], help="RSS feeds to monitor")
    parser.add_argument("--dry-run", action="store_true", help="Don't actually publish")
    parser.add_argument("--draft", action="store_true", help="Save as draft for review")
    parser.add_argument("--min-word-count", type=int, default=800)
    parser.add_argument("--min-grade", type=int, default=8)
    parser.add_argument("--max-grade", type=int, default=12)
    
    args = parser.parse_args()
    
    config = ContentConfig(
        site_url=args.site_url,
        rest_api_url=args.rest_api,
        username=args.username,
        password=args.password,
        category_id=args.category_id,
        tags=["business", "innovation", "international"],
        niche=args.niche,
        authors=args.authors,
        provider=args.provider,
        rss_feeds=args.rss,
        min_word_count=args.min_word_count,
        min_grade=args.min_grade,
        max_grade=args.max_grade
    )
    
    engine = ContentAutomationEngine(config)
    result = engine.run(dry_run=args.dry_run, draft_only=args.draft)
    
    logger.info(json.dumps(result, indent=2))
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
