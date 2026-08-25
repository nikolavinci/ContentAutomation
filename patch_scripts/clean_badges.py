with open("templates/index.html", "r", encoding="utf-8") as f:
    html = f.read()

card_cat_search = """<span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs font-bold rounded">{{ feed.category }}</span>"""
card_cat_replace = """<span class="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs font-bold rounded">
                                {{ feed.category.startsWith('Country: ') ? feed.category.replace('Country: ', '🇹🇯 ') : feed.category }}
                            </span>"""
html = html.replace(card_cat_search, card_cat_replace)

with open("templates/index.html", "w", encoding="utf-8") as f:
    f.write(html)
