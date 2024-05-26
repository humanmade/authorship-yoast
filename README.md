# Authorship for Yoast SEO

## Description
The Authorship for Yoast plugin provides schema graph and meta tag support for the [Authorship Plugin](https://github.com/humanmade/authorship). It enhances the display of author information in search engine results and improves the visibility of authors on your website.

## Installation
1. Download the latest version of the Authorship Yoast plugin from the [releases](https://github.com/humanmade/authorship-yoast/releases) page.
2. Extract the plugin files to the `/wp-content/plugins/authorship-yoast` directory.
3. Activate the plugin through the WordPress admin dashboard.

Alternatively, you can install the plugin using Composer by following these steps:
1. Open your terminal or command prompt.
2. Navigate to your WordPress project directory.
3. Run the following command to add the plugin as a dependency:
    ```bash
    composer require humanmade/authorship-yoast
    ```
4. Once the installation is complete, activate the plugin through the WordPress admin dashboard.


## FAQ

### No authors are output

Yoast SEO only outputs author data for post types with an Article schema type configured. You need to set the article schema type in the Yoast SEO Settings admin page, under the content types section.

## Usage
Works out of the box with Yoast SEO. No configuration is required.

## Contributing
Contributions are welcome! If you have any ideas, suggestions, or bug reports, please open an issue or submit a pull request on the [GitHub repository](https://github.com/humanmade/authorship-yoast).

## License
This plugin is licensed under the [GPL 3 License](https://www.gnu.org/licenses/gpl-3.0.en.html).
