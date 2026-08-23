import urllib.request
import urllib.parse
import re
import json
import concurrent.futures

# Fetch tree recursively from GitHub API
print("Fetching repository tree...")
tree_url = 'https://api.github.com/repos/plenaryapp/awesome-rss-feeds/git/trees/master?recursive=1'
req = urllib.request.Request(tree_url, headers={'User-Agent': 'Mozilla'})
tree_data = json.loads(urllib.request.urlopen(req).read().decode('utf-8'))

opml_files = [item for item in tree_data['tree'] if item['path'].endswith('.opml')]

feeds = []
print(f"Found {len(opml_files)} OPML files to process. Downloading...")

for item in opml_files:
    path = item['path']
    raw_url = f"https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/{urllib.parse.quote(path)}"
    try:
        req = urllib.request.Request(raw_url, headers={'User-Agent': 'Mozilla'})
        opml = urllib.request.urlopen(req).read().decode('utf-8')
        matches = re.findall(r'title="([^"]+)"[^>]+xmlUrl="([^"]+)"', opml)
        
        parts = path.split('/')
        if "countries" in parts:
            cat = f"Country: {parts[-1].replace('.opml', '')}"
        else:
            cat = parts[-1].replace('.opml', '')
            
        for title, xml_url in matches:
            feeds.append({"title": title, "url": xml_url, "category": cat})
    except Exception as e:
        pass

print(f"Found {len(feeds)} feeds in total. Checking health (this may take a minute)...")

def check_health(feed):
    try:
        req = urllib.request.Request(feed["url"], headers={'User-Agent': 'Mozilla/5.0'})
        res = urllib.request.urlopen(req, timeout=5)
        feed["status"] = "live" if res.getcode() == 200 else "dead"
    except Exception:
        feed["status"] = "dead"
    return feed

compiled = []
with concurrent.futures.ThreadPoolExecutor(max_workers=50) as executor:
    results = executor.map(check_health, feeds)
    for res in results:
        compiled.append(res)
        
with open('curated_feeds.json', 'w', encoding='utf-8') as f:
    json.dump(compiled, f, indent=2)

print("Saved curated_feeds.json")
