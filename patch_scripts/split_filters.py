with open("templates/index.html", "r", encoding="utf-8") as f:
    html = f.read()

filter_ui_search = """                    <div class="w-full md:w-64">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Category</label>
                        <select v-model="feedCategory" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-200 focus:outline-none bg-white">
                            <option value="">All Categories</option>
                            <option v-for="cat in uniqueFeedCategories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>"""
filter_ui_replace = """                    <div class="w-full md:w-48">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Category</label>
                        <select v-model="feedCategory" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-200 focus:outline-none bg-white">
                            <option value="">All Categories</option>
                            <option v-for="cat in uniqueFeedCategories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Country</label>
                        <select v-model="feedCountry" class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-200 focus:outline-none bg-white">
                            <option value="">All Countries</option>
                            <option v-for="country in uniqueFeedCountries" :key="country" :value="country">{{ country }}</option>
                        </select>
                    </div>"""
html = html.replace(filter_ui_search, filter_ui_replace)

data_search = """                    feedSearch: '',
                    feedCategory: '',"""
data_replace = """                    feedSearch: '',
                    feedCategory: '',
                    feedCountry: '',"""
html = html.replace(data_search, data_replace)

computed_search = """                uniqueFeedCategories() {
                    const cats = new Set();
                    this.awesomeFeeds.forEach(f => { if(f.category) cats.add(f.category); });
                    return Array.from(cats).sort();
                },"""
computed_replace = """                uniqueFeedCategories() {
                    const cats = new Set();
                    this.awesomeFeeds.forEach(f => { 
                        if(f.category && !f.category.startsWith('Country:')) cats.add(f.category); 
                    });
                    return Array.from(cats).sort();
                },
                uniqueFeedCountries() {
                    const countries = new Set();
                    this.awesomeFeeds.forEach(f => { 
                        if(f.category && f.category.startsWith('Country:')) {
                            countries.add(f.category.replace('Country: ', ''));
                        }
                    });
                    return Array.from(countries).sort();
                },"""
html = html.replace(computed_search, computed_replace)

filter_search = """                    if (this.feedCategory) {
                        result = result.filter(f => f.category === this.feedCategory);
                    }"""
filter_replace = """                    if (this.feedCategory) {
                        result = result.filter(f => f.category === this.feedCategory);
                    }
                    
                    if (this.feedCountry) {
                        result = result.filter(f => f.category === 'Country: ' + this.feedCountry);
                    }"""
html = html.replace(filter_search, filter_replace)

with open("templates/index.html", "w", encoding="utf-8") as f:
    f.write(html)
print("Updated UI to split country and category")
