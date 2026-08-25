with open("wp_content_engine.py", "r", encoding="utf-8") as f:
    code = f.read()

# 1. Update the prompt to explicitly request H1/H2 tags and featured image heading
prompt_search = """<Start writing the main article content here in Nepali using markdown formatting (H2, H3, bullet points).>"""
prompt_replace = """<Start writing the main article content here in Nepali using markdown formatting. 
Important:
- Start with a clear H1 or H2 title for the main section.
- Use proper H2 and H3 tags to organize the content.
- Include a bold placeholder text like **[Featured Image Here]** near the top if appropriate.
- Ensure the structure is extremely well-organized for a blog post.>"""
code = code.replace(prompt_search, prompt_replace)


# 2. Update publish logic to safely inject SEO meta
publish_search = """            data = {
                "title": article.title,
                "content": html_content,
                "status": "draft" if draft_only else "publish",
                "categories": cat_ids,
                "tags": tag_ids,
            }
            if article.metadata.get("url_slug"):
                data["slug"] = article.metadata["url_slug"]
                
            endpoint = f"{self.config.rest_api_url}/posts"
            resp = requests.post(endpoint, json=data, auth=self.session.auth, timeout=30)
            resp.raise_for_status()
            post_id = resp.json()["id"]
            post_url = resp.json()["link"]"""

publish_replace = """            data = {
                "title": article.title,
                "content": html_content,
                "status": "draft" if draft_only else "publish",
                "categories": cat_ids,
                "tags": tag_ids,
            }
            if article.metadata.get("url_slug"):
                data["slug"] = article.metadata["url_slug"]
                
            # Create post first without meta to ensure it succeeds
            endpoint = f"{self.config.rest_api_url}/posts"
            resp = requests.post(endpoint, json=data, auth=self.session.auth, timeout=30)
            resp.raise_for_status()
            post_id = resp.json()["id"]
            post_url = resp.json()["link"]
            
            # Secondary request to inject SEO Meta elements safely (RankMath / Yoast)
            try:
                meta_data = {
                    "meta": {
                        "yoast_wpseo_title": article.title,
                        "yoast_wpseo_metadesc": article.metadata.get("meta_description", ""),
                        "yoast_wpseo_focuskw": ", ".join(article.metadata.get("focus_keywords", [])),
                        "rank_math_title": article.title,
                        "rank_math_description": article.metadata.get("meta_description", ""),
                        "rank_math_focus_keyword": ", ".join(article.metadata.get("focus_keywords", []))
                    }
                }
                meta_resp = requests.post(f"{endpoint}/{post_id}", json=meta_data, auth=self.session.auth, timeout=10)
                if meta_resp.status_code == 200:
                    logger.info("Successfully injected SEO meta fields into the CMS.")
            except Exception as e:
                logger.warning(f"Could not inject SEO meta (this is normal if Yoast/RankMath REST APIs are not exposed): {e}")"""
code = code.replace(publish_search, publish_replace)

with open("wp_content_engine.py", "w", encoding="utf-8") as f:
    f.write(code)
print("Patched SEO elements into wp_content_engine.py")
