=== VM Social AI ===
Contributors: vmstudiocreatives
Tags: social media, ai, automation, seo, instagram, facebook, linkedin, youtube
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.17.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An autonomous social media engine for WordPress: two AI engines with failover, a business Brain, a campaign planner, and hands-off publishing to six networks.

== Description ==

VM Social AI plans, writes, illustrates, schedules and publishes social content without supervision, then reads the results back and feeds them into the next batch.

**Engine one — words.** Requests run down an ordered chain: AI Puffer (AIPKit REST) → Google Gemini → OpenRouter → NVIDIA NIM → Ollama. When one rate limits or fails, the next takes over in the same request; the post is never dropped. Model lists are pulled live from each provider twice a day, so the dropdowns show what your account can call today.

**Engine two — pictures.** AI Puffer → Pollinations → ComfyUI → Pexels. Generation first, licensed stock last, so a scheduled post never ships without media. Every image lands in the media library with a keyword-first filename, alt text, title and caption.

**The Brain.** A durable record of the business: audience, offers with prices, proof, voice, banned phrases, keywords. It reads your site and drafts itself, then injects into every prompt. It also remembers what has already been published so the planner never repeats an angle, and which posts performed so it can lean into what worked.

**The planner.** Give it a view target and a deadline. It shows you the arithmetic first — how many posts that implies and what each one would need to average — then writes a dated, per-channel, per-pillar plan across six content pillars and refills itself as the campaign runs.

**The critic.** Every draft is linted for stock AI phrasing, missing keywords, over-length copy and anything the Brain marks as forbidden. Failures are rewritten before they reach the queue.

**Channels.** Facebook Pages, Instagram, X, LinkedIn organisation pages, Google Business Profile and YouTube Shorts.

== Changelog ==

= 1.17.0 =
* Fixed: undefined VMSAI_Video_Engine::produce() fatal on video-format posts (TikTok, YouTube, Instagram, Facebook).
* Security: Telegram webhook callback now verifies the chat owner before processing approvals; callback data parsing hardened.
* Security: credential save/save-tests are staged and rolled back on failure, so a failed engine or channel test no longer clobbers saved keys.
* Security: settings-form credentials (R2, outbound proxy, GitHub token) now require the vmsai_manage_keys capability.
* Security: stored XSS in the admin text/video engine test panels (third-party error output and raw model text were injected unescaped).
* Security: universal test license keys now only activate when VMSAI_DEV_TEST_KEYS is defined.
* Fixed: portal can no longer approve/un-publish posts in processing, failed or published states; portal media renders as video for MP4s; share links now expire after two weeks.
* Fixed: Facebook Reels upload rebuilt to the real three-phase video_reels protocol (was corrupting the binary as a multipart form post).
* Fixed: LinkedIn/YouTube/R2 uploads stream from disk instead of loading whole videos into memory; LinkedIn PUT now sends the required Content-Type.
* Fixed: Bluesky post dates use the ATProto format and blob uploads send an explicit Content-Type; Threads polls container status instead of a blind sleep.
* Fixed: composer now has real specs for Threads, Bluesky, TikTok, Telegram and Pinterest (was cutting everything to Facebook limits).
* Fixed: inbox timestamps normalized to MySQL format for ISO8601 and unix-time sources.
* Fixed: analytics stores zero-metric rows (flops show as 0, not "no data") but stops re-polling posts that have been all-zero for a week.
* Fixed: video engine reaches full operation — usage tracking, circuit breakers, non-blocking polling, FFmpeg drawtext escaping, OmniRoute/HeyGen/SVD/Luma/Minimax provider config detection, saved avatar/voice honored.
* Fixed: image engine no longer force-appends Pollinations against the configured chain; canvas sizes added for Threads, Bluesky, TikTok, Telegram and Pinterest.
* Fixed: scheduler applies retry backoff, reverts rows stranded by daily caps/quiet hours/circuit breakers, cleanup no longer deletes draft/failed media, NULL-safe evergreen recycle.
* Fixed: uninstall now removes all tables, crons, options and transients.
* Fixed: GitHub updater works on PHP 7.4, backs off after repeated failures instead of hanging wp-admin, and no longer renames unrelated directories during bulk updates.
* Fixed: Anthropic uses the live model catalogue instead of a retired model ID; AIPuffer integration survives AIPKit API changes; Gemini image base64 validated strictly; Cloudflare Workers AI config detection corrected.
* Fixed: crons fully unscheduled on deactivation; plugin action links hooked; orphan autoloader directory removed; stray XML state file deleted; research guard added for filtered providers.

= 1.16.1 =
* Fixed: fatal call to undefined VMSAI_Http::is_local_host().

== Installation ==

1. Upload the folder to `/wp-content/plugins/` and activate.
2. Open **VM Social AI → Brain** and press "Read my site and draft this", then correct what it got wrong.
3. Open **Engines** and add at least one text key. Pollinations needs no key, so images work immediately.
4. Open **Channels** and connect the networks you want.
5. Open **Plan**, set a target and a deadline, and start the campaign.

Real cron is strongly recommended over WP-Cron. Disable the built-in scheduler in `wp-config.php`:

    define( 'DISABLE_WP_CRON', true );

and add a system cron entry:

    */5 * * * * cd /path/to/site && wp cron event run --due-now > /dev/null 2>&1

== Security ==

API keys are encrypted with AES-256-GCM before they are written to the database, keyed off your WordPress salts. Any credential can instead be defined as a constant in `wp-config.php` — for example `VMSAI_GEMINI_KEY` — and a constant always takes priority over the stored value. All REST routes require `manage_options`, all forms are nonce-checked, and secrets are redacted from the log table.

== A note on view targets ==

The plugin will publish whatever volume you ask of it, on schedule, indefinitely. It cannot promise a reach number, because reach is decided by the platforms and by whether the content is worth watching.

What it does instead is show you the arithmetic before you commit. Enter a target and it tells you how many posts that implies and what each one would need to average, against typical organic reach for an account your size. When the target outruns the maths, it says so plainly and names the three levers that actually close the gap: more posts per day, short-form video rather than static images, and paid amplification behind the posts that already earned attention organically.

Then it tracks pace daily against the line, so you find out in week one whether you are behind — not in week seven.

== Frequently Asked Questions ==

= Do I need to pay for AI? =

No. Pollinations handles images free and keyless, OpenRouter's catalogue includes zero-cost models, and Ollama runs on your own VPS at no per-call cost. Put the free providers at the end of the chain and paid ones first, or drop the paid ones entirely.

= What happens if a provider goes down mid-campaign? =

Failures are counted per provider. After a threshold the provider is benched for a cooldown period and the chain skips it, so a dead key degrades quality slightly instead of stopping the plugin. Published posts that fail retry with exponential backoff before being retired.

= Does it need ffmpeg? =

Only for YouTube Shorts, which renders a vertical clip from the generated still. Everything else works without it.

== Changelog ==

= 1.0.0 =
* First release.
