# WP AI Site Assistant

A lightweight MVP WordPress plugin that adds an admin-side AI assistant with:

- chat-like admin UI
- OpenAI Responses API planning
- safe action proposal flow
- audit logs
- basic WordPress tools:
  - create draft post/page/product
  - update content by post ID
  - list recent content

## Important

This is an MVP starter, not a full autonomous agent. It is intentionally limited to reduce risk.

## Installation

1. Zip the `wp-ai-site-assistant` folder.
2. In WordPress go to Plugins > Add New > Upload Plugin.
3. Activate **WP AI Site Assistant**.
4. Open **AI Assistant** in wp-admin.
5. Add your OpenAI API key in Settings.

## Notes about OpenAI integration

The plugin uses the OpenAI **Responses API** and function calling style planning. Function calling is supported in the Responses API, and GPTs are intended for ChatGPT while product integrations should use the API. citeturn251011search0turn251011search3

## Current limitations

- no media upload tool yet
- no Elementor-specific support yet
- no plugin/theme installer tools
- no role-based granularity beyond `manage_options`
- no background tasks, cron, or queues
- no advanced approval history screen

## Suggested next steps

- add tool registry UI
- add capability matrix per tool
- add Elementor-aware content tools
- add WooCommerce product meta tools
- add soft-delete and rollback
- move API key to environment variable support
- add per-site system prompts
