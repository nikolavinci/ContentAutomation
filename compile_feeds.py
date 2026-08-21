import urllib.request
import re
import json
import concurrent.futures

sources = [
    'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/without_category/Tech.opml',
    'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/without_category/Business%20%26%20Economy.opml',
    'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/without_category/Science.opml',
    'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/without_category/News.opml'
]

feeds = []
for src in sources:
    try:
        req = urllib.request.Request(src, headers={'User-Agent': 'Mozilla'})
        opml = urllib.request.urlopen(req).read().decode('utf-8')
        matches = re.findall(r'title="([^"]+)"[^>]+xmlUrl="([^"]+)"', opml)
        cat = src.split('/')[-1].replace('.opml', '').replace('%20', ' ').replace('%26', '&')
        for title, xml_url in matches:
            feeds.append({"title": title, "url": xml_url, "category": cat})
    except Exception as e:
        print(f"Failed to fetch {src}: {e}")

print(f"Found {len(feeds)} feeds. Checking health...")

def check_health(feed):
    try:
        req = urllib.request.Request(feed["url"], headers={'User-Agent': 'Mozilla/5.0'})
        res = urllib.request.urlopen(req, timeout=5)
        feed["status"] = "live" if res.getcode() == 200 else "dead"
    except Exception:
        feed["status"] = "dead"
    return feed

compiled = []
with concurrent.futures.ThreadPoolExecutor(max_workers=20) as executor:
    results = executor.map(check_health, feeds)
    for res in results:
        compiled.append(res)
        
with open('curated_feeds.json', 'w', encoding='utf-8') as f:
    json.dump(compiled, f, indent=2)

print("Saved curated_feeds.json")
