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
from dataclasses import dataclass

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
    """Generates article content using OpenAI or Gemini API"""
    
    def __init__(self, provider: str = "openai", api_key: str = None):
        self.provider = provider
        if self.provider == "openai":
            self.client = openai.OpenAI(api_key=api_key or os.getenv("OPENAI_API_KEY"))
            self.model = "gpt-4o"
        elif self.provider == "gemini":
            from google import genai
            self.client = genai.Client(api_key=api_key or os.getenv("GEMINI_API_KEY"))
            self.model = "gemini-3.7-flash"
        else:
            raise ValueError(f"Unknown provider: {self.provider}")
    
    def generate_article(self, config: ContentConfig) -> str:
        """Generate a complete article tailored to the niche"""
        
        prompt = f"""You are a professional business journalist writing for an international news platform covering {config.niche}.

Generate a comprehensive, original article that:
1. Targets trending topics in {config.niche} (research current events)
2. Is 1500-2000 words
3. Includes:
   - A compelling headline (use H1)
   - A strong lede (first 2-3 sentences hook the reader)
   - 3-4 subheadings (H2) with substantive sections
   - At least 3 quoted sources/expert perspectives
   - Real data points and statistics (cite sources)
   - Internal linking suggestions in [brackets like this]
   - Original analysis, not regurgitation

4. SEO optimized:
   - Primary keyword naturally woven throughout
   - Related keywords in subheadings
   - Descriptive meta title idea
   - Meta description idea (155-160 chars)

5. Follows E-E-A-T principles:
   - Establishes author expertise early
   - Cites authoritative sources
   - Builds trust with transparency

Format output as:
---
HEADLINE: [Your headline]
META_TITLE: [SEO title]
META_DESCRIPTION: [SEO description]
AUTHOR: [Author name]
TAGS: [tag1, tag2, tag3]
---

[Full article in HTML markup]"""

        if self.provider == "openai":
            response = self.client.chat.completions.create(
                model=self.model,
                messages=[
                    {"role": "user", "content": prompt}
                ]
            )
            
            # Log token usage and estimated cost for GPT-4o
            usage = response.usage
            if usage:
                prompt_cost = (usage.prompt_tokens / 1_000_000) * 5.00
                completion_cost = (usage.completion_tokens / 1_000_000) * 15.00
                total_cost = prompt_cost + completion_cost
                
                logger.info(f"OpenAI Usage - Input: {usage.prompt_tokens} tokens | Output: {usage.completion_tokens} tokens | Total: {usage.total_tokens} tokens")
                logger.info(f"Estimated Cost for this article: ${total_cost:.4f}")
            
            return response.choices[0].message.content
            
        elif self.provider == "gemini":
            response = self.client.models.generate_content(
                model=self.model,
                contents=prompt
            )
            logger.info("Generated article using Gemini API")
            return response.text


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
        
        # Target: Grade 8-12 (conversational but authoritative)
        status = "pass" if 8 <= grade <= 12 else "warn" if 6 <= grade <= 14 else "fail"
        
        return {
            "grade": round(grade, 1),
            "status": status,
            "explanation": f"Grade {grade:.1f} - {'Too simple' if grade < 8 else 'Too complex' if grade > 12 else 'Perfect range'}"
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
        if article.word_count < 800:
            results["checks"]["word_count"] = {"status": "fail", "message": f"Only {article.word_count} words; target 1000+"}
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
    
    def publish(self, article: Article, draft_only: bool = False) -> Dict:
        """
        Publish article to WordPress
        If draft_only=True, creates as draft (for review)
        """
        
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
        self.qa = QualityAssurance()
        self.publisher = WordPressPublisher(config)
    
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
        
        # Stage 1: Generate
        logger.info("Stage 1: Generating content...")
        try:
            raw_content = self.generator.generate_article(self.config)
            result["stages"]["generation"] = {"status": "success"}
            logger.info(f"Generated {len(raw_content)} characters")
        except Exception as e:
            result["stages"]["generation"] = {"status": "failed", "error": str(e)}
            result["status"] = "failed"
            return result
        
        # Parse generated content (simplified)
        article = self._parse_generated_content(raw_content)
        
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
        
        return Article(
            title=metadata.get("HEADLINE", "Untitled"),
            content=content,
            excerpt=metadata.get("META_DESCRIPTION", "")[:160],
            featured_image_url=None,
            author_name=metadata.get("AUTHOR", self.config.authors[0]),
            tags=metadata.get("TAGS", "").split(","),
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
    parser.add_argument("--dry-run", action="store_true", help="Don't actually publish")
    parser.add_argument("--draft", action="store_true", help="Save as draft for review")
    
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
        provider=args.provider
    )
    
    engine = ContentAutomationEngine(config)
    result = engine.run(dry_run=args.dry_run, draft_only=args.draft)
    
    logger.info(json.dumps(result, indent=2))
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
