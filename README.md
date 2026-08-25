# Content Automaton (WordPress Plugin)

**Content Automaton** is a fully automated, AI-powered content generation and publishing engine for WordPress. It natively connects your WordPress site to the OpenAI (GPT-4o) and Google Gemini (1.5 Pro) APIs to dynamically aggregate, synthesize, and publish highly optimized, multi-lingual SEO articles on a scheduled basis.

Originally built as a standalone Python architecture, it has been fully ported and refactored into a native, standalone WordPress plugin.

## 🚀 Features

* **Native WordPress Integration:** MVC-style plugin architecture (`nca-*`) running entirely within WordPress. No external servers or Python environments required.
* **Smart Content Aggregation:** Pulls from custom RSS feeds, competitor sitemaps, and Google News to find trending niche topics.
* **Anti-Duplication Safety Engine:** Maintains a capped history cache (`NCA_processed_urls`) to ensure the exact same source URL is never processed twice, saving you valuable AI API tokens.
* **Contextual Internal Linking:** Automatically queries your database for recent published posts and seamlessly weaves contextual anchor text links into the generated AI prose to boost your SEO.
* **Dual LLM Fallback:** Attempts to generate via OpenAI first, gracefully falling back to Gemini if the first request fails or times out.
* **Built-in SEO Fallback:** Dynamically injects native `<title>` and `<meta description>` tags if third-party SEO plugins (like Yoast or RankMath) are not detected.
* **Automatic Media Generation:** Integrates with Pollinations.ai to generate royalty-free, niche-specific featured images and sideloads them directly into your WordPress Media Library.
* **Modern Shadcn UI Dashboard:** A clean, modern settings dashboard injected natively into WP-Admin, complete with inline scheduling options, password toggles, and token usage logs.
* **Dynamic WP-Cron Integration:** Customize execution intervals down to the minute, hour, or day.
* **Multi-Language Support:** Type-to-search datalist support for auto-translating content into any target language.

## 📦 Installation

We have included a pre-compiled `.zip` file for immediate deployment.

1. Download the `content-automaton.zip` file directly from this repository.
2. Log into your WordPress Admin Dashboard.
3. Navigate to **Plugins > Add New Plugin > Upload Plugin**.
4. Choose the `content-automaton.zip` file and click **Install Now**.
5. Click **Activate**.
6. A new menu item called **Content Automaton** will appear in your sidebar.

## ⚙️ Configuration

1. **Dashboard & API:** Enter your OpenAI API Key, Gemini API Key, and (optional) Telegram Bot Token for push notifications. 
   *(Note: API keys are securely stored in your WordPress database and are fully masked in the UI).*
2. **RSS Feeds:** Define the target RSS feeds or sitemaps you wish the plugin to crawl.
3. **Taxonomy & SEO:** Set your default Category ID, target language, and comma-separated tags.
4. **Automation Schedule:** Turn the Master Switch **ON** and set your desired generation frequency (e.g., Every 4 Hours).

## 📊 Monitoring

* **AI Content Library:** A dedicated submenu to view all generated drafts and published articles exclusively created by Content Automaton.
* **Logs & Usage:** Track historical generation runs, token consumption, and estimated API costs in real-time.
* **Crawled History:** Verify which external source URLs have been successfully fetched and cached to prevent duplicates.

## 🛡️ Security

This repository does **not** contain any hardcoded API keys, `.env` files, or private tokens. All credentials are user-supplied via the WordPress UI and strictly stored inside the `wp_options` table on your individual server.

## 📂 Legacy Code

The original standalone Python architecture has been preserved in the `python_backend/` directory for historical reference and backup purposes. Temporary migration and patching scripts are located in `patch_scripts/`.
