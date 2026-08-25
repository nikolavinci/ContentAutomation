import json

with open("curated_feeds.json", "r", encoding="utf-8") as f:
    feeds = json.load(f)

countries = [
    'Australia', 'Bangladesh', 'Brazil', 'Canada', 'France', 'Germany', 
    'Hong Kong SAR China', 'India', 'Indonesia', 'Iran', 'Ireland', 'Italy', 
    'Japan', 'Mexico', 'Myanmar (Burma)', 'Nigeria', 'Pakistan', 'Philippines', 
    'Poland', 'Russia', 'South Africa', 'Spain', 'Ukraine', 'United Kingdom', 'United States'
]

count = 0
for feed in feeds:
    if feed["category"] in countries:
        feed["category"] = "Country: " + feed["category"]
        count += 1

with open("curated_feeds.json", "w", encoding="utf-8") as f:
    json.dump(feeds, f, indent=2)

print(f"Patched {count} feeds with Country prefix.")
