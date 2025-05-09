# Neurame AI Assistant

**Neurame AI Assistant** is a WordPress plugin designed to help parents, educators, and administrators manage children's
information, track progress, and receive AI-powered course recommendations. Integrated with WooCommerce and AI APIs (
ChatGPT or Gemini), it provides a user-friendly dashboard for managing children, generating trainer reports, and
suggesting tailored educational courses.

## Features

- **Child Management**: Add, edit, and manage children's profiles (name, age, interests).
- **AI-Powered Course Recommendations**: Get personalized course suggestions based on a child's age, interests, and
  parental goals using ChatGPT or Gemini APIs.
- **Trainer Reports**: Record and analyze trainer reports with optional AI-enhanced rewriting.
- **Progress Tracking**: Monitor children's skill development and progress through intelligent analytics.
- **WooCommerce Integration**: Link recommended courses to WooCommerce product pages for seamless enrollment.
- **User Dashboard**: A dedicated dashboard (`/my-account/neurame-dashboard/`) for parents and admins to manage all
  features.
- **Shortcodes**: Easily embed forms and displays (e.g., `[neurame_children]`, `[neurame_recommend_course]`) in pages or
  posts.
- **Admin Tools**: Manage settings, generate course syllabi, and view analytics from the WordPress admin panel.

## Prerequisites

To use **Neurame AI Assistant**, ensure the following requirements are met:

1. **WordPress**:
	- Minimum version: 5.0 (latest version recommended).
	- PHP version: 7.4 or higher.
2. **WooCommerce**:
	- Minimum version: 5.0 (latest version recommended).
	- Must be installed and activated.
3. **AI API (Optional)**:
	- For AI-powered features, obtain an API key from:
		- **ChatGPT**: [OpenAI Platform](https://platform.openai.com/).
		- **Gemini**: [Google Generative AI](https://ai.google.dev/).
4. **Server**:
	- Write access to `wp-content` for debug logs (`neurame-debug.log`).
	- PHP functions `file_put_contents` and `chmod` enabled.
5. **Basic Knowledge**:
	- Familiarity with WordPress plugin installation and basic PHP/JavaScript for customization.

## Installation

### Step 1: Install the Plugin

1. **Download or Create Plugin Folder**:
	- Create a folder named `neurame-ai-assistant` in `wp-content/plugins/`.
	- Place the plugin files (e.g., `neurame-ai-assistant.php`) in this folder.
2. **Plugin Header**:
   Ensure the main plugin file (`neurame-ai-assistant.php`) includes the following header:

   ```php
   <?php
   /*
   Plugin Name: Neurame AI Assistant
   Plugin URI: https://example.com
   Description: A plugin for managing children, reports, and AI-powered course recommendations
   Version: 1.2.0
   Author: Your Name
   Author URI: https://example.com
   Text Domain: neurame-ai-assistant
   Domain Path: /languages
   */
   ```

3. **Assets**:
	- Create an `assets` folder (`wp-content/plugins/neurame-ai-assistant/assets/`) with:
		- `css/neurame-styles.css` (and `.min.css` for minified version).
		- `js/neurame-child.js`, `js/neurame-report.js`.
4. **Partials**:
	- Create a `partials` folder (`wp-content/plugins/neurame-ai-assistant/partials/`) with:
		- `children-form.php`: Form for managing children.
		- `trainer-reports-template.php`: Template for trainer reports in admin.
5. **Activate Plugin**:
	- Go to `wp-admin/plugins.php`, find **Neurame AI Assistant**, and click **Activate**.

### Step 2: Initial Setup

1. **Plugin Settings**:
	- Navigate to **Neurame AI** in the WordPress admin menu (`wp-admin/admin.php?page=neurame-ai-assistant`).
	- Configure:
		- **Parental Mode**: Enable to restrict parents to managing only their children; disable for admins to view all
		  users/children.
		- **Analytics**: Enable for statistical analysis.
		- **AI API Type**: Choose **ChatGPT**, **Gemini**, or **None**.
		- Enter API keys for ChatGPT or Gemini if selected.
	- Click **Save Settings**.
2. **Debug Logging**:
	- To enable logging, add to `wp-config.php`:
	  ```php
      define('NEURAMEAI_DEBUG_LOG', true);
      ```
	- Logs are saved to `wp-content/neurame-debug.log`.
3. **WooCommerce Products**:
	- Ensure WooCommerce has published products (courses) for recommendations (`wp-admin/edit.php?post_type=product`).

## Usage

### User Dashboard

- **Access**: Log in to WooCommerce account (`/my-account/`) and navigate to **Parent Smart Dashboard** (
  `/my-account/neurame-dashboard/`).
- **Features**:
	- **Parent Profile**: Set educational goals for children.
	- **Child Management**: Add/edit children (name, age, interests).
	- **Course Assistant**: Request AI-powered course recommendations.
	- **Recommended Courses**: View suggested courses with links to WooCommerce product pages.
	- **Progress Reports**: View children's progress and trainer reports.

### Shortcodes

Embed plugin features in pages or posts using these shortcodes:

- `[neurame_profile]`: Display parent profile form.
- `[neurame_children]`: Display child management form.
- `[neurame_smart_assistant]`: Display AI course recommendation form.
- `[neurame_recommend_course]`: Display recommended courses.
- `[neurame_child_progress]`: Display children's progress.
- `[neurame_ai_recommendation]`: Display AI recommendation form.

**Example**:
To add a child management form:

1. Create a new page (e.g., "Manage Children").
2. Add `[neurame_children]` to the content.
3. Publish and visit the page.

### Admin Features

- **Settings**: Configure plugin options under **Neurame AI** (`wp-admin/admin.php?page=neurame-ai-assistant`).
- **Smart Syllabus Generator**: Generate AI-powered syllabi for WooCommerce courses (
  `Neurame AI > Smart Syllabus Generator`).
- **Trainer Reports**: Record and manage trainer reports (`Neurame AI > Trainer Reports`).
- **Child Management**: View/edit children (non-parental mode only, `Neurame AI > Child Management`).
- **Analytics**: View statistics like course count, trainers, and children (`Neurame AI > Analytics`).
- **Course Metabox**: View/edit AI-generated syllabi in WooCommerce product edit pages (`Course AI Information`).

## Development and Customization

### Project Structure

```
neurame-ai-assistant/
├── assets/
│   ├── css/
│   │   └── neurame-styles.css
│   ├── js/
│   │   ├── neurame-child.js
│   │   └── neurame-report.js
├── partials/
│   ├── children-form.php
│   └── trainer-reports-template.php
└── neurame-ai-assistant.php
```

### Adding a New Shortcode

1. In `NeurameAIAssistant::__construct`, register the shortcode:
   ```php
   add_shortcode('neurame_custom_shortcode', [$this, 'render_custom_shortcode']);
   ```
2. Add the shortcode method:
   ```php
   public function render_custom_shortcode() {
       ob_start();
       ?>
       <div class="neurame-custom-shortcode">
           <p><?php esc_html_e('Custom shortcode content.', 'neurame-ai-assistant'); ?></p>
       </div>
       <?php
       return ob_get_clean();
   }
   ```
3. Use the shortcode: `[neurame_custom_shortcode]`.

### Adding an AJAX Request

1. In `NeurameAIAssistant::__construct`, register the AJAX action:
   ```php
   add_action('wp_ajax_neurame_custom_action', [$this, 'handle_custom_action']);
   ```
2. Add the AJAX handler:
   ```php
   public function handle_custom_action() {
       check_ajax_referer('neurame_custom_action', 'nonce');
       wp_send_json_success(['message' => __('Request processed!', 'neurame-ai-assistant')]);
   }
   ```
3. Add nonce to JavaScript in `enqueue_scripts`:
   ```php
   $neurame_vars['nonce_custom_action'] = wp_create_nonce('neurame_custom_action');
   ```
4. Send AJAX request in `neurame-report.js`:
   ```javascript
   const fd = new FormData();
   fd.append('action', 'neurame_custom_action');
   fd.append('nonce', neurame_vars.nonce_custom_action);
   fetch(neurame_vars.ajax_url, { method: 'POST', body: fd })
       .then(resp => resp.json())
       .then(json => {
           if (json.success) {
               showToast(json.data.message, 'success');
           }
       });
   ```

### Styling

- CSS files are located in `assets/css/neurame-styles.css`.
- Use Tailwind CSS classes (e.g., `bg-blue-600`, `hover:bg-blue-700`) for consistency.
- Example:
  ```css
  .neurame-button {
      @apply bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors;
  }
  ```

## Debugging

- Enable debug logging in `wp-config.php`:
  ```php
  define('NEURAMEAI_DEBUG_LOG', true);
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```
- Check logs:
	- Plugin logs: `wp-content/neurame-debug.log`.
	- WordPress logs: `wp-content/debug.log`.
- Common issues:
	- **Missing API Key**: Ensure valid ChatGPT/Gemini API keys are set.
	- **No Courses**: Verify WooCommerce has published products.
	- **Invalid Permalinks**: Set permalinks to `/postname/` in `wp-admin/options-permalink.php`.

## Troubleshooting

- **No Course Recommendations**:
	- Check API settings and key validity.
	- Ensure WooCommerce products are published.
	- Review `neurame-debug.log` for errors (e.g., `Invalid JSON Format`).
- **Missing "View Course" Button**:
	- Verify permalinks work (`wp-admin/options-permalink.php`).
	- Check `course_url` in `neurame-debug.log` (look for `Course Loaded` or `Valid Course Added`).
	- Ensure products have valid IDs matching `course_id` in logs.
- **AJAX Errors**:
	- Inspect browser console (`Network` tab) for `admin-ajax.php` response.
	- Check `debug.log` for PHP errors.

## Contributing

1. Fork the repository (if hosted on GitHub).
2. Create a feature branch (`git checkout -b feature/new-feature`).
3. Commit changes (`git commit -m 'Add new feature'`).
4. Push to the branch (`git push origin feature/new-feature`).
5. Create a pull request.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Support

For issues, feature requests, or questions:

- Create an issue on the [GitHub repository](https://github.com/Hossein4163/Neurame-AI-Assistant) (if applicable).
- Contact the author at [ramestudio.yzd@gmail.com](mailto:ramestudio.yzd@gmail.com).

---

**Neurame AI Assistant** - Empowering education through AI and WordPress.