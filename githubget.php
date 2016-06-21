<?php
/*
Plugin Name:       GitHub Get
Plugin URI:        https://github.com/yoander/githubget
Description:       GitHub Get is a WordPress plugin for fetching content from GitHub. GitHub Get use GitHub personal token and basic authentication to access the GitHub API.
Version:           0.1.6
Author:            Yoander Valdés Rodríguez (libreman)
License:           GNU General Public License v3
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
Domain Path:       /languages
Text Domain:       githubget
GitHub Plugin URI: https://github.com/yoander/githubget
GitHub Branch:     master
*/

define('PLUGIN_DIR', plugins_url() . '/' . dirname(plugin_basename(__FILE__)));
define('GITHUBGET_API', 'https://api.github.com');

/**
 * Plugin Installation:
 *   - create configuration keys
 */
function githubget_install() {
    add_option('githubget_option', [
        'github_user' => '',
        'github_token' => '',
    ]);
}
register_activation_hook(__FILE__, 'githubget_install');


/**
 * Plugin Deinstallation
 *   - delete configuration keys
 */
function githubget_deinstall() {
    delete_option('githubget_option');
}

register_deactivation_hook(__FILE__, 'githubget_deinstall');


/**
 * Get option of this plugin
 */
function githubget_get_option($item) {
    $res = get_option('githubget_option');
    if (empty($res) || !isset($res[$item]))
        return null;
    return $res[$item];
}

/**
 * Set option of this plugin
 */
function githubget_set_option($item, $val) {
    $res = get_option('githubget_option');
    if (empty($res))
        $res = array();
    $res[$item] = $val;
    update_option('githubget_option', $res);
    return $val;
}

/**
 * Initialize Localization Functions
 */
function githubget_load_textdomain() {
    if (function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain('githubget', false, dirname(plugin_basename( __FILE__ )) . '/' . 'languages');
    }
}
add_action('init', 'githubget_load_textdomain');

/**
 * Add Settings Page to Admin Menu
 */
function githubge_admin_page() {
    if (function_exists('add_submenu_page'))
        add_options_page(__('Gitub Get Settings'), __('Gitub Get '), 'manage_options', 'githubget', 'githubget_settings_page');
}
add_action('admin_menu', 'githubge_admin_page');


/**
 * Add Settings link to plugin page
 */
function githubget_add_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
      $links[] = '<a href="options-general.php?page=githubget">' . __('Settings') . '</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'githubget_add_settings_link', 10, 2);


function githubget_on_update_complete($plugin, $data) {
    if (!empty($data) && !empty($data['type']) && 'plugin' == $data['type'] && 'update' == $data['action']) {
        $this_file_name = basename(__FILE__);
        $rebuild_flag = false;
        foreach($data['plugins'] as $updated_file) {
            if ($this_file_name == basename($updated_file)) {
                $rebuild_flag = true;
            }
        }
    }
}
add_action('upgrader_process_complete', 'githubget_on_update_complete', 10, 2);

/**
 * Add shortcode function
 *
 */
function githubget_func( $atts, $content = '' ) {
    if (empty($content)) {
        return '';
    }

    $atts =  isset($atts) && is_array($atts) ? $atts : array();

    $args = shortcode_atts( array(
        'filename'    => '',
        'repo'        => false,
        'ribbon'      => false,
        'container' => '',
    ), $atts);

    //  var_dump($args['container']); return;
    if (!defined('GITHUBGET_USER')) {;
        define('GITHUBGET_USER',  githubget_get_option('github_user'));
    }

    if (!defined('GITHUBGET_TOKEN')) {
        define('GITHUBGET_TOKEN',  githubget_get_option('github_token'));
    }

    $is_repo = strtolower($args['repo']);
    $is_repo = '1' == $is_repo || 'true' == $is_repo ? true: false;
    if ($is_repo) {
        $resource =  GITHUBGET_API . '/repos/' . GITHUBGET_USER;
        $pathparts = explode('/', $content);

        $reponame = array_shift($pathparts);

        $filepath = !empty($pathparts) ? implode('/', $pathparts) : '';

        $resource .= "/$reponame/contents/$filepath";
    } else {
        $resource =  GITHUBGET_API . "/gists/$content";
    }

    $reqargs = array(
        'headers' => array(
            'Authorization' => 'token ' . GITHUBGET_TOKEN
        )
    );

    $response = wp_remote_get( $resource, $reqargs );
    // Grab URL and pass it to the browser
    if ($github_data = wp_remote_retrieve_body( $response )) {

        // Get body response
        $github_data = json_decode($github_data, true);
        // No error decoding json
        if (JSON_ERROR_NONE == json_last_error()) {
            $has_ribbon =  '1' == $args['ribbon'] || 'true' == $args['ribbon'] ? true: false;

            // For file in a repo
            if ($is_repo) {
                if (isset($github_data['content'])) {
                    $result = htmlspecialchars(base64_decode($github_data['content']));
                } else {
                    $result = 'Invalid repo file: %s %s, <a href="https://github.com/%s">Repos</a>';
                    $result = sprintf($result, $content, '(' . $github_data['message'] . ')', GITHUBGET_USER);
                    $has_ribbon = false;
                }
            } elseif (!empty($github_data['files'])) { // For file in a Gists
                if ($filename = $args['filename']) {
                     // Remove simple/double quote from filename attribute
                    $filename = str_replace(['&quot;', '&#34;', '"', '&apos;', '&#039;', "'"], '', $args['filename']);

                    if (isset($github_data['files'][$filename])) {
                        $result = htmlspecialchars($github_data['files'][$filename]['content']);
                    } else {
                        $result = 'Invalid file name: %s, <a href="https://gist.github.com/%s/%s">Gist</a>';
                        $result = sprintf($result, $filename, GITHUBGET_USER, $content);
                        $has_ribbon = false;
                    }
                } else{
                    $result = htmlspecialchars(reset($github_data['files'])['content']);
                }
            } else {
                $result = 'Invalid Gist: %s %s, <a href="https://gist.github.com/%s">Gists</a>';
                $result = sprintf($result, $content, '('. $github_data['message'] . ')', GITHUBGET_USER);
                $has_ribbon = false;
            }
        } else {
            $result = json_last_error_msg();
            $has_ribbon = false;
        }
    } else {
        $result = 'Content can not be get from ' . $resource;
        $has_ribbon = false;
    }

    $container = [];
    if (!empty($args['container'])) {
        $tags = explode('.', $args['container']);
        foreach ($tags as $tag) {
            $classes = '';
            $style = '';
            if (preg_match_all("/\(((\w+[-_\s]?)+)\)|\{((\s*(\w+-?)+\s*:\s*(\w+-?)+;?)+)\s*\}/", $tag, $matches, PREG_SET_ORDER)) {
                $searches = [
                    $matches[0][0],
                    isset($matches[1][0]) ? $matches[1][0] : ''
                ];
                $tag = str_replace($searches, '', $tag);
                $classes = ' class="' . $matches[0][1] . '"';
                $style = isset($matches[1][3]) ? ' style="' . $matches[1][3] . '"' : '';

            }
            $container['start_tags'][] = "<$tag$classes$style>";
            $container['end_tags'][] = "</$tag>";
        }

        $result = implode('', [
            implode('', $container['start_tags']),
            $result,
            implode('', array_reverse($container['end_tags']))
        ]);
    }

    if ($has_ribbon) {
        $result = sprintf(
            '<a target="_blank" class="ribbon" href="%s">[%s %s]</a>%s',
            $github_data['html_url'],
            '&#955;',
            empty($args['ribbontitle']) ? 'Fork me on Github' : $args['ribbontitle'],
            "\n") . $result;
    }

    return $result;
}

add_shortcode( 'githubget', 'githubget_func' );
add_shortcode( 'ghget', 'githubget_func' );

/**
 * Settings Page
 */
function githubget_settings_page() {
    if (isset( $_POST['cmd'] ) && $_POST['cmd'] == 'githubget_save')
    {
        $upload_options = array(
            'github_user' => $_POST['github_user'],
            'github_token' => $_POST['github_token']
        );

        update_option('githubget_option', $upload_options);
        echo '<p class="info">' . __('All configurations successfully saved...', 'githubget_option') . '</p>';
    }
?>

    <!-- html code of settings page -->

    <div class="wrap">
      <form id="githubget" method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>">
        <!-- text edit : additional css -->
       <table>
          <tr>
            <td><label for="hljs_additional_css"><?php echo __('Github User', 'githubget'); ?></label></td>
            <td><input type="text" name="github_user" id="github_user_id" value="<?php echo githubget_get_option('github_user') ?>" /></td>
          </tr>
          <tr>
           <td><?php echo __('Github Token', 'githubget'); ?></label></td>
           <td><input type="text" name="github_token" id="github_token_id" value="<?php echo githubget_get_option('github_token') ?>" /></td>
          </tr>
       </table>
        <input type="hidden" name="cmd" value="githubget_save" />
        <input type="submit" name="submit" value="<?php echo __('Save', 'githubget'); ?>" id="submit" />
      </form>
    </div>

    <!-- /html code of settings page -->

<?php
}
