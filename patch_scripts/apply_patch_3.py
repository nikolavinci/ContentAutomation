with open("wp_content_engine.py", "r", encoding="utf-8") as f:
    code = f.read()

publish_data_search = """            data = {
                "title": article.title,
                "content": html_content,
                "status": "draft" if draft_only else "publish",
                "categories": cat_ids,
                "tags": tag_ids,
            }"""

publish_data_replace = """            data = {
                "title": article.title,
                "content": html_content,
                "status": "draft" if draft_only else "publish",
                "categories": cat_ids,
                "tags": tag_ids,
            }
            if article.metadata.get("url_slug"):
                data["slug"] = article.metadata["url_slug"]"""

code = code.replace(publish_data_search, publish_data_replace)

with open("wp_content_engine.py", "w", encoding="utf-8") as f:
    f.write(code)
print("Applied Python patch 3")
