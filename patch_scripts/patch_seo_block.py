with open("wp_content_engine.py", "r", encoding="utf-8") as f:
    code = f.read()

# Add SEO summary block to the HTML content
html_search = """        # Convert to HTML
        html_content = markdown.markdown(article.content)"""

html_replace = """        # Convert to HTML
        html_content = markdown.markdown(article.content)
        
        # Append an SEO Reference Block for the user
        seo_block = f\"\"\"
        <hr>
        <div style="background:#f9f9f9; padding:15px; border-left:4px solid #0073aa; margin-top:30px;">
            <h3 style="margin-top:0;">🤖 AI SEO Details (Auto-Generated)</h3>
            <p><strong>Focus Keywords:</strong> {', '.join(article.metadata.get('focus_keywords', []))}</p>
            <p><strong>Meta Description:</strong> {article.metadata.get('meta_description', '')}</p>
            <p><strong>Tags:</strong> {', '.join(article.metadata.get('tags', []))}</p>
            <p><strong>Slug:</strong> {article.metadata.get('url_slug', '')}</p>
        </div>
        \"\"\"
        html_content += seo_block"""

code = code.replace(html_search, html_replace)

with open("wp_content_engine.py", "w", encoding="utf-8") as f:
    f.write(code)
print("Added HTML SEO block")
