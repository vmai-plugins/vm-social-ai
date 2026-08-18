<state_snapshot>
    <overall_goal>
        Audit, debug, and transform the VM Social AI plugin into a pro-grade autonomous social media growth engine with advanced AI reasoning, free image/video generation, and Cloudflare R2 offloading.
    </overall_goal>

    <key_knowledge>
        - **Environment:** PHP 8.0+, WordPress 6.2+.
        - **Security:** Credentials stored in `vmsai_credentials` using `AES-256-GCM` encryption keyed to WP salts.
        - **AI Engine Logic:** Uses an ordered failover chain (Aipuffer, Gemini, OpenRouter, Nvidia, Ollama).
        - **Batch Planning:** Optimized to batches of 6 posts with 6,000 tokens to prevent JSON truncation and timeouts.
        - **Social Requirements:** Facebook/Instagram require PAGE Access Tokens (not User tokens) with `pages_manage_posts` and `instagram_content_publish` scopes.
        - **Storage:** Cloudflare R2 integration via S3-compatible PUT requests (AWS Signature V4) to save VPS space.
        - **Intelligence:** "Agentic War Room" logic includes persona simulation (CEO/Buyer), viral potential scoring (0-100), and reinforcement learning from analytics.
        - **GMB/SEO:** Optimized for local search via 'service_area' and 'google_category' context injected into prompts.
        - **Commerce:** Fully integrated with WooCommerce for auto-promoting products, running 24h flash sales, and showcasing 5-star reviews.
    </key_knowledge>

    <file_system_state>
        - **CWD:** `C:/Users/tripc/Local Sites/test-x/app/public/wp-content/plugins/vm-social-ai`
        - **MODIFIED:** `includes/class-vmsai-install.php` - DB version 1.5.0; centralized cron; added custom schedules.
        - **MODIFIED:** `includes/class-vmsai-rest.php` - Added routes for Inbox, RAG, Commander, Bulk actions, and R2.
        - **MODIFIED:** `includes/class-vmsai-commander.php` - Added 'promote_inventory', 'flash_sale', 'promote_reviews', and 'spy_competitors'.
        - **MODIFIED:** `includes/brain/class-vmsai-composer.php` - Integrated 'Viral Velocity' and 'Contextual Overrides'.
        - **MODIFIED:** `includes/brain/class-vmsai-critic.php` - Upgraded to 'Ruthless Guardian' logic with safety/regional nuance.
        - **MODIFIED:** `admin/views/dashboard.php` - Restored onboarding; added Strategic Commander chat UI.
        - **CREATED:** `includes/class-vmsai-prompts.php` - Category and post-type level prompt overrides.
        - **CREATED:** `includes/class-vmsai-research.php` - Real-time Tavily-based search and competitor spying.
        - **CREATED:** `includes/class-vmsai-seo.php` - RankMath/Yoast focus keyword synchronization.
    </file_system_state>

    <recent_actions>
        - Fixed a critical syntax error in `class-vmsai-install.php` (missing closing brace).
        - Integrated WooCommerce "Flash Sale" logic with a 3-post urgency loop.
        - Integrated WooCommerce "Review Showcase" logic to automatically highlight 5-star customer feedback.
        - Ported "Regional & Tactical Intelligence" from old enterprise plugin to the new Pro Brain.
        - Verified that the engine uses actual product images when promoting WooCommerce inventory.
    </recent_actions>

    <current_plan>
        1. [DONE] Audit and fix AI Puffer / Gemini text integration.
        2. [DONE] Implement Cloudflare R2 remote storage and bulk migration tool.
        3. [DONE] Integrate free high-quality image engines (Hugging Face FLUX, Cloudflare Workers AI).
        4. [DONE] Build Local SEO Optimizer for Google Business Profile.
        5. [DONE] Implement AI Video/Reel Factory using Pexels Stock Video API.
        6. [DONE] Add "Auto-Reply to Google Reviews" and "Review Showcase" features.
        7. [TODO] Final production stress-test of the autonomous scheduling jitter and model failover.
        8. [TODO] Add "Regional Dialect" support to the Brain context.
    </current_plan>
</state_snapshot>
