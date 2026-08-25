with open("templates/index.html", "r", encoding="utf-8") as f:
    html = f.read()

nav_search = """                <button @click="currentTab = 'drafts'" :class="{'bg-gray-800 text-blue-400': currentTab === 'drafts', 'hover:bg-gray-800': currentTab !== 'drafts'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-file-alt w-6"></i> Saved Drafts
                </button>"""
nav_replace = """                <button @click="currentTab = 'drafts'" :class="{'bg-gray-800 text-blue-400': currentTab === 'drafts', 'hover:bg-gray-800': currentTab !== 'drafts'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-file-alt w-6"></i> Saved Drafts
                </button>
                <button @click="loadMedia(); currentTab = 'media'" :class="{'bg-gray-800 text-blue-400': currentTab === 'media', 'hover:bg-gray-800': currentTab !== 'media'}" class="w-full text-left px-4 py-3 rounded-lg transition-colors flex items-center">
                    <i class="fas fa-images w-6"></i> Media Library
                </button>"""
if "Media Library" not in html:
    html = html.replace(nav_search, nav_replace)

tab_search = """            <!-- Tab: Settings -->"""
tab_replace = """            <!-- Tab: Media Library -->
            <div v-if="currentTab === 'media'" class="p-8 flex-1 overflow-y-auto bg-gray-50">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Media Library</h2>
                <div v-if="mediaFiles.length === 0" class="text-center mt-10">
                    <i class="fas fa-images text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No media assets generated yet.</p>
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div v-for="file in mediaFiles" :key="file.filename" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden group">
                        <div class="h-48 bg-gray-100 relative">
                            <img :src="'/media/' + file.filename" class="w-full h-full object-cover transition-transform group-hover:scale-105" loading="lazy">
                        </div>
                        <div class="p-4">
                            <p class="text-sm font-bold text-gray-800 truncate mb-1" :title="file.filename">{{ file.filename }}</p>
                            <div class="flex justify-between items-center text-xs text-gray-500 mb-3">
                                <span>{{ (file.size / 1024).toFixed(1) }} KB</span>
                                <span>{{ new Date(file.created * 1000).toLocaleDateString() }}</span>
                            </div>
                            <div class="flex space-x-2">
                                <a :href="'/media/' + file.filename" target="_blank" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-1 rounded text-sm transition-colors">Preview</a>
                                <a :href="'/media/' + file.filename" :download="file.filename" class="flex-1 text-center bg-blue-100 hover:bg-blue-200 text-blue-800 py-1 rounded text-sm transition-colors">Download</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Settings -->"""
html = html.replace(tab_search, tab_replace)

data_search = """                    drafts: [],"""
data_replace = """                    drafts: [],
                    mediaFiles: [],"""
html = html.replace(data_search, data_replace)

methods_search = """                async loadDrafts() {"""
methods_replace = """                async loadMedia() {
                    try {
                        const res = await fetch('/api/media');
                        const data = await res.json();
                        if (data.success) {
                            this.mediaFiles = data.media;
                        }
                    } catch(e) { console.error(e); }
                },
                async loadDrafts() {"""
html = html.replace(methods_search, methods_replace)

with open("templates/index.html", "w", encoding="utf-8") as f:
    f.write(html)
print("Updated index.html with Media tab")
