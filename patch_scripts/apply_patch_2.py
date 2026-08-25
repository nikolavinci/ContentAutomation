with open("wp_content_engine.py", "r", encoding="utf-8") as f:
    code = f.read()

parse_search = """    def _parse_generated_content(self, raw_content: str) -> Article:
        \"\"\"Parses the LLM output into an Article object\"\"\"
        title = ""
        metadata = {
            "focus_keywords": [],
            "tags": [],
            "categories": [],
            "twitter_post": "",
            "linkedin_post": "",
            "meta_description": ""
        }
        
        content_lines = []
        in_metadata = False
        
        for line in raw_content.split('\\n'):
            line_stripped = line.strip()
            
            if line_stripped == "---":
                in_metadata = not in_metadata
                continue
                
            if in_metadata:
                if line_stripped.startswith("HEADLINE:"):
                    title = line_stripped.replace("HEADLINE:", "").strip()
                elif line_stripped.startswith("META_DESCRIPTION:"):
                    metadata["meta_description"] = line_stripped.replace("META_DESCRIPTION:", "").strip()
                elif line_stripped.startswith("FOCUS_KEYWORDS:"):
                    kws = line_stripped.replace("FOCUS_KEYWORDS:", "").strip()
                    metadata["focus_keywords"] = [k.strip() for k in kws.split(',') if k.strip()]
                elif line_stripped.startswith("TAGS:"):
                    tags = line_stripped.replace("TAGS:", "").strip()
                    metadata["tags"] = [t.strip() for t in tags.split(',') if t.strip()]
                elif line_stripped.startswith("CATEGORIES:"):
                    cats = line_stripped.replace("CATEGORIES:", "").strip()
                    metadata["categories"] = [c.strip() for c in cats.split(',') if c.strip()]
                elif line_stripped.startswith("TWITTER_POST:"):
                    metadata["twitter_post"] = line_stripped.replace("TWITTER_POST:", "").strip()
                elif line_stripped.startswith("LINKEDIN_POST:"):
                    metadata["linkedin_post"] = line_stripped.replace("LINKEDIN_POST:", "").strip()
            else:
                content_lines.append(line)"""

parse_replace = """    def _parse_generated_content(self, raw_content: str) -> Article:
        \"\"\"Parses the LLM output into an Article object\"\"\"
        title = ""
        metadata = {
            "focus_keywords": [],
            "tags": [],
            "categories": [],
            "url_slug": "",
            "image_prompt": "",
            "twitter_post": "",
            "linkedin_post": "",
            "meta_description": ""
        }
        
        content_lines = []
        in_metadata = False
        
        for line in raw_content.split('\\n'):
            line_stripped = line.strip()
            
            if line_stripped == "---":
                in_metadata = not in_metadata
                continue
                
            if in_metadata:
                if line_stripped.startswith("HEADLINE:"):
                    title = line_stripped.replace("HEADLINE:", "").strip()
                elif line_stripped.startswith("META_DESCRIPTION:"):
                    metadata["meta_description"] = line_stripped.replace("META_DESCRIPTION:", "").strip()
                elif line_stripped.startswith("FOCUS_KEYWORDS:"):
                    kws = line_stripped.replace("FOCUS_KEYWORDS:", "").strip()
                    metadata["focus_keywords"] = [k.strip() for k in kws.split(',') if k.strip()]
                elif line_stripped.startswith("TAGS:"):
                    tags = line_stripped.replace("TAGS:", "").strip()
                    metadata["tags"] = [t.strip() for t in tags.split(',') if t.strip()]
                elif line_stripped.startswith("CATEGORIES:"):
                    cats = line_stripped.replace("CATEGORIES:", "").strip()
                    metadata["categories"] = [c.strip() for c in cats.split(',') if c.strip()]
                elif line_stripped.startswith("TWITTER_POST:"):
                    metadata["twitter_post"] = line_stripped.replace("TWITTER_POST:", "").strip()
                elif line_stripped.startswith("LINKEDIN_POST:"):
                    metadata["linkedin_post"] = line_stripped.replace("LINKEDIN_POST:", "").strip()
                elif line_stripped.startswith("URL_SLUG:"):
                    metadata["url_slug"] = line_stripped.replace("URL_SLUG:", "").strip()
                elif line_stripped.startswith("IMAGE_PROMPT:"):
                    metadata["image_prompt"] = line_stripped.replace("IMAGE_PROMPT:", "").strip()
                elif line_stripped.startswith("SUBTITLE:"):
                    subtitle = line_stripped.replace("SUBTITLE:", "").strip()
                    if subtitle:
                        content_lines.append(f"**{subtitle}**\\n")
            else:
                content_lines.append(line)"""

code = code.replace(parse_search, parse_replace)

with open("wp_content_engine.py", "w", encoding="utf-8") as f:
    f.write(code)
print("Applied Python patch 2")
