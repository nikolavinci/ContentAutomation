with open("templates/index.html", "r", encoding="utf-8") as f:
    html = f.read()

nav_search = """                <button @click="loadDrafts(); currentTab = 'drafts'" :class="{'bg-gray-800 text-blue-400': currentTab === 'drafts', 'hover:bg-gray-800': currentTab !== 'drafts'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-file-alt w-6"></i> Saved Drafts
                </button>"""
nav_replace = """                <button @click="loadDrafts(); currentTab = 'drafts'" :class="{'bg-gray-800 text-blue-400': currentTab === 'drafts', 'hover:bg-gray-800': currentTab !== 'drafts'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-file-alt w-6"></i> Saved Drafts
                </button>
                <button @click="loadMedia(); currentTab = 'media'" :class="{'bg-gray-800 text-blue-400': currentTab === 'media', 'hover:bg-gray-800': currentTab !== 'media'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-images w-6"></i> Media Library
                </button>"""

if "Media Library" not in html or "currentTab = 'media'" not in html[:1000]:
    html = html.replace(nav_search, nav_replace)

with open("templates/index.html", "w", encoding="utf-8") as f:
    f.write(html)
print("Added media library button")
