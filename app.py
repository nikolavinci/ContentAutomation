import json
import os
import glob
import yaml
import subprocess
from flask import Flask, render_template, request, jsonify

app = Flask(__name__)

CONFIG_FILE = "sites.yaml"
LOG_FILE = "content_automation.log"

def load_config():
    if not os.path.exists(CONFIG_FILE):
        return {}
    with open(CONFIG_FILE, 'r') as f:
        return yaml.safe_load(f) or {}

def save_config(data):
    with open(CONFIG_FILE, 'w') as f:
        yaml.dump(data, f, default_flow_style=False)

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/config', methods=['GET'])
def get_config():
    return jsonify(load_config())

@app.route('/api/config', methods=['POST'])
def update_config():
    try:
        data = request.json
        save_config(data)
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 400


@app.route('/api/sites', methods=['POST'])
def add_site():
    try:
        data = request.json
        site_name = data.get("name")
        config_data = data.get("config")
        
        current = load_config()
        current[site_name] = config_data
        save_config(current)
        
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 400

@app.route('/api/sites/<site_name>', methods=['DELETE'])
def delete_site(site_name):
    try:
        current = load_config()
        if site_name in current:
            del current[site_name]
            save_config(current)
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 400

@app.route('/api/logs', methods=['GET'])
def get_logs():
    if not os.path.exists(LOG_FILE):
        return jsonify({"logs": []})
    
    # Return last 100 lines
    with open(LOG_FILE, 'r') as f:
        lines = f.readlines()
        return jsonify({"logs": [line.strip() for line in lines[-100:]]})

@app.route('/api/logs/clear', methods=['POST'])
def clear_logs():
    with open(LOG_FILE, 'w') as f:
        f.write("")
    return jsonify({"success": True})


@app.route('/api/drafts', methods=['GET'])
def list_drafts():
    try:
        drafts = []
        if os.path.exists("drafts"):
            files = glob.glob("drafts/*.md")
            files.sort(reverse=True, key=os.path.getmtime)
            for file in files:
                drafts.append({
                    "filename": os.path.basename(file),
                    "created": os.path.getmtime(file)
                })
        return jsonify({"success": True, "drafts": drafts})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/drafts/<filename>', methods=['GET'])
def get_draft(filename):
    try:
        safe_path = os.path.abspath(os.path.join("drafts", filename))
        if not safe_path.startswith(os.path.abspath("drafts")):
            return jsonify({"success": False, "error": "Invalid file"}), 400
            
        if os.path.exists(safe_path):
            with open(safe_path, 'r', encoding="utf-8") as f:
                content = f.read()
            return jsonify({"success": True, "content": content})
        return jsonify({"success": False, "error": "Not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.route('/api/awesome-rss', methods=['GET'])
def awesome_rss():
    try:
        if not os.path.exists('curated_feeds.json'):
            return jsonify({"success": False, "error": "Feeds not compiled yet"}), 404
        with open('curated_feeds.json', 'r', encoding='utf-8') as f:
            feeds = json.load(f)
        return jsonify({"success": True, "feeds": feeds})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


scheduler_process = None

@app.route('/api/scheduler/status', methods=['GET'])
def get_scheduler_status():
    global scheduler_process
    is_running = scheduler_process is not None and scheduler_process.poll() is None
    return jsonify({"success": True, "is_running": is_running})

@app.route('/api/scheduler/toggle', methods=['POST'])
def toggle_scheduler():
    global scheduler_process
    is_running = scheduler_process is not None and scheduler_process.poll() is None
    if is_running:
        scheduler_process.terminate()
        scheduler_process = None
        return jsonify({"success": True, "is_running": False})
    else:
        try:
            env = os.environ.copy()
            full_config = load_config()
            g = full_config.get("global", {})
            if g.get("openai_api_key"): env["OPENAI_API_KEY"] = g.get("openai_api_key")
            if g.get("gemini_api_key"): env["GEMINI_API_KEY"] = g.get("gemini_api_key")
            scheduler_process = subprocess.Popen(["python", "wp_scheduler.py", "--config", "sites.yaml"], env=env)
            return jsonify({"success": True, "is_running": True})
        except Exception as e:
            return jsonify({"success": False, "error": str(e)}), 500


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

@app.route('/api/trigger', methods=['POST'])
def trigger_run():
    data = request.json
    site_name = data.get("site")
    draft_only = data.get("draft_only", True)
    provider = data.get("provider", "openai")
    
    config = load_config().get(site_name)
    if not config:
        return jsonify({"success": False, "error": "Site not found"}), 404
        
    import sys
    cmd = [
        sys.executable, "wp_content_engine.py",
        "--site-url", config.get("site_url") or "N/A",
        "--rest-api", config.get("rest_api_url") or "N/A",
        "--username", config.get("username") or "N/A",
        "--password", config.get("password") or "N/A",
        "--niche", config.get("niche") or "N/A",
        "--provider", provider,
        "--min-word-count", str(config.get("min_word_count", 800)),
        "--min-grade", str(config.get("min_grade", 8)),
        "--max-grade", str(config.get("max_grade", 12))
    ]
    
    authors = config.get("authors", ["Admin"])
    if authors:
        cmd.append("--authors")
        cmd.extend(authors)
        
    rss_feeds = config.get("rss_feeds", [])
    if rss_feeds:
        cmd.append("--rss")
        cmd.extend(rss_feeds)
    
    if draft_only:
        cmd.append("--draft")
        
    # Start as background process
    try:
        # Note: In production you'd use Celery, but subprocess works for a local tool.
        env = os.environ.copy()
        full_config = load_config()
        g = full_config.get("global", {})
        if g.get("openai_api_key"): env["OPENAI_API_KEY"] = g.get("openai_api_key")
        if g.get("gemini_api_key"): env["GEMINI_API_KEY"] = g.get("gemini_api_key")
        if g.get("anthropic_api_key"): env["ANTHROPIC_API_KEY"] = g.get("anthropic_api_key")
        if g.get("grok_api_key"): env["GROK_API_KEY"] = g.get("grok_api_key")
        if g.get("kimi_api_key"): env["KIMI_API_KEY"] = g.get("kimi_api_key")
        
        subprocess.Popen(cmd, env=env)
        return jsonify({"success": True, "message": f"Started background generation for {site_name}"})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)
