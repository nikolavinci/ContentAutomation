# Content Automaton (WordPress Plugin) - V2

**Content Automaton** is a fully automated, AI-powered content generation and publishing engine for WordPress. It natively connects your WordPress site to the best LLM providers (OpenAI, Gemini, Groq, DeepSeek, Qwen) to dynamically aggregate, synthesize, and publish highly optimized, multi-lingual SEO articles on a scheduled basis.

Originally built as a standalone Python architecture, it has been fully ported and refactored into a scalable, multi-stage WordPress plugin (V2 Architecture).

## 🚀 Features (V2 Updates)

* **Advanced Multi-Step Queue System:** Background processing is split into atomic stages (Discovery -> Fetch -> Clustering -> Generation) to prevent timeouts and optimize API usage.
* **Smart Content Clustering:** Groups related trending news articles together into a single cluster to synthesize comprehensive pillar content.
* **Multi-Provider LLM Support:** Easily switch between OpenAI, Google Gemini, Groq (for blazing-fast generation), DeepSeek, and Alibaba Qwen.
* **Real API Image Integrations:** Uses Unsplash, Pexels, and Pixabay APIs to fetch real, high-quality featured images (replacing legacy AI image generation).
* **Anti-Duplication Safety Engine:** Maintains a rigorous hash-based history cache to ensure the exact same source content is never processed twice.
* **Advanced UI Dashboard & Filtering:** A clean, modern settings dashboard injected natively into WP-Admin with advanced dynamic filtering for System Logs and Archive statuses.
* **Dynamic WP-Cron Integration:** Customize execution intervals down to the minute, hour, or day.

## 📦 Installation

We have included a pre-compiled `.zip` file for immediate deployment.

1. Download the `content-automaton-v3.5.2.zip` file directly from this repository.
2. Log into your WordPress Admin Dashboard.
3. Navigate to **Plugins > Add New Plugin > Upload Plugin**.
4. Choose the `content-automaton-v3.5.2.zip` file and click **Install Now**.
5. Click **Activate**.
6. A new menu item called **Content Automaton** will appear in your sidebar.

## ⚙️ Configuration

1. **API Keys:** Enter your chosen LLM API Keys (OpenAI, Gemini, Groq, DeepSeek, or Qwen) and Image Provider API Keys (Unsplash, Pexels, Pixabay). 
   *(Note: API keys are securely stored in your WordPress database and are masked in the UI).*
2. **RSS Feeds:** Define the target RSS feeds you wish the plugin to crawl.
3. **Taxonomy & SEO:** Set your default Category ID, slug language, and meta configurations.
4. **Automation Schedule:** Set your Engine Status to **Running** and configure your desired generation frequency (e.g., Every 1 Hours).

## 📊 Monitoring

* **Archive & Drafts:** View the complete processing pipeline of clustered articles and monitor their exact processing status (Pending, Clustered, Draft Created, Completed). Filters added for quick sorting.
* **System Logs:** Track the background queue engine in real-time. Filter logs easily by Level (INFO, ERROR, SUCCESS), Action (FETCH, GENERATION, etc.), or Date.
* **Usage Dashboard:** Track historical generation runs, token consumption, and estimated API costs.

## 🛡️ Security

This repository does **not** contain any hardcoded API keys, `.env` files, or private tokens. All credentials are user-supplied via the WordPress UI and strictly stored inside the `wp_options` table on your individual server.

## 📂 Legacy Code

The original standalone Python architecture has been preserved in the `python_backend/` directory for historical reference and backup purposes. Temporary migration and patching scripts are located in `patch_scripts/`.
