=== OliForge Polls ===
Contributors: Oleksandr Lilik
Tags: polls, voting, shortcode, gutenberg, rest-api
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create responsive polls with shortcode, Gutenberg support, Elementor widget, REST voting, results and CSV export.

== Description ==

OliForge Polls lets you create a poll as a custom post type, add a question, add any number of answers, collect votes, and display results as votes and percentages.

Main features:

* Poll custom post type.
* Shortcode output: [oliforge-polls id="123"].
* Alternative shortcode by generated short ID: [oliforge-polls id="456"] when shown in the poll list.
* React-based poll editor in the Poll edit screen.
* Start date and end date.
* Result visibility modes: after vote, after end, always, hidden.
* REST API based voting.
* Custom database tables for vote rows and vote counters.
* Atomic vote counter updates.
* Reset votes, recount results, and CSV export tools.
* Gutenberg-ready dynamic block scaffold.
* Elements widget for displaying a poll in widget areas.
* Admin list columns with question, answers count, votes, dates, and copyable shortcode.

== Installation ==

1. Upload the `oliforge-polls` folder to `/wp-content/plugins/`, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate OliForge Polls.
3. Go to Polls > Add New.
4. Add a question, answers, dates, labels, and styles.
5. Publish the poll.
6. Copy the shortcode from the Polls list or use the post ID.

== Usage ==

Basic shortcode:

`[oliforge-polls id="123"]`

Where `123` is the Poll post ID or the generated shortcode ID shown in the Polls list.

Recommended workflow:

1. Create a new Poll.
2. Enter the question.
3. Add at least two answers.
4. Choose when results should be visible.
5. Optionally add a start date and end date.
6. Publish.
7. Insert the shortcode into any page, post, widget area, or compatible builder.

== Result Visibility Modes ==

* After vote: visitors see the form first and results after voting. Results are also shown when the poll is closed.
* After end: results are hidden until the end date.
* Always: results are visible even before voting.
* Hidden: public result output is hidden.

== Admin Tools ==

On the Poll edit screen, OliForge Polls provides:

* Export CSV: downloads raw vote rows.
* Recount results: rebuilds counters from vote rows.
* Reset votes: removes all vote rows and counters for the poll.

== Database Tables ==

OliForge Polls creates two custom tables on activation:

* `{prefix}_oliforge_polls_votes` stores individual vote rows.
* `{prefix}_oliforge_polls_vote_counts` stores aggregated counters per answer.

The counter table is used for fast result display. The vote table is used for duplicate checks, export, and recounting.

== Security Notes ==

Version 0.6.0 includes additional hardening:

* SQL values are inserted through `$wpdb->prepare()`, `$wpdb->insert()`, and `$wpdb->delete()`.
* Admin actions use nonces and `edit_post` capability checks.
* REST voting requires a WordPress REST nonce.
* Public REST result output respects the poll result visibility mode.
* CSV export escapes cells that could trigger spreadsheet formulas.
* Anonymous voter keys are stored as HMAC hashes instead of raw cookie tokens.
* Inline style output is filtered with WordPress safe CSS filtering.

Limitations:

* Anonymous duplicate protection is still Lite-level protection. It uses cookies and can be bypassed by clearing cookies or changing browser/device.
* Public shortcode, block, widget, Elementor widget and REST rendering is restricted to published polls for public visitors.
* Draft, pending, private and trashed polls are hidden on the front end unless the current user can edit the poll.
* The vote REST endpoint includes transient-based rate limiting. Default: 10 vote attempts per poll per minute per IP/user-agent fingerprint. Customize with the oliforge_polls_vote_rate_limit and oliforge_polls_vote_rate_window filters.
* For high-stakes polls, consider logged-in voting, CAPTCHA/Turnstile, or stricter server-side throttling.

== Changelog ==

= 0.10.0 =
* Renamed the Vote custom post type and its labels to Poll (menu, list headings, add/edit/search screens) to match the shortcode, settings page, and the rest of the plugin's terminology; "Vote"/"Votes" is now reserved for the actual vote-count/tally features.
* Renamed the "Voter Builder" meta box to "Poll Builder" to match.
* Added the branded OliForge header (logo, tagline, version badge) above the Polls list table.
* Wrapped the Polls list table screen (title, filters, table, pagination) in a white card matching the settings page styling.
* Rebranded the Polls list table's interactive colors (row actions, sortable column headers and sort arrows, All/Published filters, Add New/Apply/Filter buttons) from WordPress's default blue to the OliForge orange palette; the poll title itself stays dark since it reads as a heading, not a link.
* Fixed a specificity bug that left a stray default WordPress button border around the Shortcode column's copy icon.

= 0.9.0 =
* Renamed and rebranded the plugin from OliPoll to OliForge Polls, including the CPT slug, database table names, post meta keys, shortcode tag, Gutenberg block name, Elementor widget slug, REST namespace and widget id_base.
* Fixed the frontend stylesheet enqueue pointing at a non-existent file, so poll styling now loads correctly on the front end.
* Removed the non-functional Container "Text color" style control; it was always overridden by the more specific question/answer/button/results colors.
* Live Preview now shows two separate blocks: the poll appearance/voting-form preview, and a separate Live Results card with standard admin styling that also displays the poll question as a heading.

= 0.8.14 =
* Moved every Voter Builder control directly into its matching tab panel.
* Removed the remaining accordion panel wrappers and duplicate section headings from the tab content.

= 0.8.3 =
* Updated custom table SQL queries to use WordPress identifier placeholders (`%i`) for Plugin Check compatibility.
* Hardened direct database query construction for vote counters, vote lookups, recounts, exports, and table cleanup.

= 0.8.2 =
* Added Votes → Settings data retention option.
* Plugin data is preserved on uninstall by default.
* Full data deletion on uninstall only runs when the admin explicitly enables Delete data on uninstall.
* Hardened uninstall table cleanup with a fixed table whitelist.

= 0.8.1 =
* Security hardening: front-end shortcode, widget, Elementor widget, block and REST access now require published polls for public visitors.
* Added transient-based REST vote rate limiting with HTTP 429 responses.
* Cleaned release packaging from macOS metadata.

= 0.8.0 =
* Added Elements widget for displaying a poll in widget areas.
* Added admin list columns with question, answers count, votes, dates, and copyable shortcode.
* Added shortcode view in the Edit Vote page.
* Added Copy functionality for shortcodes in the list and edit screens.

= 0.7.0 =
Replaced the old Gulp-oriented workflow with @wordpress/scripts.
Added src/ source structure for admin editor, admin list, frontend, and block editor scripts.
Added webpack.config.js with multiple entry points.
Added build/ assets and *.asset.php files for WordPress dependency metadata.
Updated PHP enqueue logic to load compiled assets from build/.

= 0.6.0 =
* Security review and hardening.
* Hardened public REST results endpoint so hidden results are not exposed through the API.
* Added CSV formula-injection protection.
* Anonymous voter keys are now hashed before DB storage.
* Added stricter anonymous voter cookie validation.
* Added safe CSS filtering for inline styles.
* Expanded README with version history, usage, security notes, and database details.

= 0.5.0 =
* Added vote start date.
* Added result visibility modes: after vote, after end, always, hidden.
* Added reset votes tool.
* Added recount results tool.
* Added CSV export.
* Added copy shortcode button in the Vote list screen.

= 0.4.0 =
* Replaced admin-ajax voting with REST API endpoints.
* Added `POST /wp-json/oliforge-polls/v1/polls/{id}/vote`.
* Added `GET /wp-json/oliforge-polls/v1/polls/{id}/results`.
* Added REST nonce support in frontend voting.

= 0.3.0 =
* Added custom database tables for vote storage and counters.
* Added atomic counter updates.
* Migrated result reads from post meta counters to database counters.
* Added migration from old answer meta counts into counter table.

= 0.2.0 =
* Added end date.
* After voting, the form is hidden and current results are shown.
* Closed polls show final results.
* Added Gutenberg-ready dynamic block scaffold.

= 0.1.0 =
* Initial MVP.
* Added Vote custom post type.
* Added shortcode rendering.
* Added React-based Vote editor.
* Added basic styles and answer management.
