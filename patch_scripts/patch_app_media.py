import os
with open("app.py", "r", encoding="utf-8") as f:
    code = f.read()

# Replace get_draft to serve media if it's an image, or create a new endpoint
media_api = """
@app.route('/api/media', methods=['GET'])
def list_media():
    try:
        media = []
        if os.path.exists("drafts"):
            for f in os.listdir("drafts"):
                if f.lower().endswith(('.webp', '.jpg', '.jpeg', '.png')):
                    path = os.path.join("drafts", f)
                    stat = os.stat(path)
                    media.append({
                        "filename": f,
                        "size": stat.st_size,
                        "created": stat.st_mtime
                    })
            media.sort(reverse=True, key=lambda x: x["created"])
        return jsonify({"success": True, "media": media})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/media/<filename>', methods=['GET'])
def get_media_file(filename):
    try:
        return send_from_directory('drafts', filename)
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 404
"""

if "/api/media" not in code:
    # Insert before the trigger route
    code = code.replace("@app.route('/api/trigger', methods=['POST'])", media_api + "\n@app.route('/api/trigger', methods=['POST'])")

with open("app.py", "w", encoding="utf-8") as f:
    f.write(code)
print("Added media API to app.py")
