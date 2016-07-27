<?php
/*
Plugin Name:       GitHub Get
Plugin URI:        https://github.com/yoander/githubget
Description:       GitHub Get is a WordPress plugin for fetching content from GitHub. GitHub Get use GitHub personal token and basic authentication to access the GitHub API.
Version:           0.1.7
Author:            Yoander Valdés Rodríguez (libreman)
License:           GNU General Public License v3
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
Domain Path:       /languages
Text Domain:       githubget
GitHub Plugin URI: https://github.com/yoander/githubget
GitHub Branch:     master
*/

define('GITHUBGET_URL', plugins_url() . '/' . dirname(plugin_basename(__FILE__)));
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
function githubget_admin_page() {
    if (function_exists('add_submenu_page'))
        add_options_page(__('Gitub Get Settings'), __('Gitub Get '), 'manage_options', 'githubget', 'githubget_settings_page');
}
add_action('admin_menu', 'githubget_admin_page');


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

function githubget_include() {
    wp_enqueue_style( 'githubget_style', GITHUBGET_URL . '/css/style.css');

}
add_action('wp_head', 'githubget_include');

/**
 * Build the API endpoint
 *
 * @param  string  $user    API user
 * @param  string  $content Content idenfier
 * @param  boolean $is_repo If a repo or Gist
 * @return string           Endpoint url
 */
function get_resource( $user, $content, $is_repo = false) {
    if ($is_repo) {
        $resource =  GITHUBGET_API . '/repos/' . $user;
        $pathparts = explode('/', $content);

        $reponame = array_shift($pathparts);

        $filepath = !empty($pathparts) ? implode('/', $pathparts) : '';

        $resource .= "/$reponame/contents/$filepath";
    } else {
        $resource =  GITHUBGET_API . "/gists/$content";
    }

    return $resource;
}

/**
 * Decode de response
 *
 * @param  [type]  $body    [description]
 * @param  boolean $is_repo [description]
 * @return array           [description]
 */
function process_response_body( $body, $file_name = '', $is_repo = false ) {
    // Convert JSON to array
    $github_data = json_decode( $body, true );
    // No error decoding JSON?
    if (JSON_ERROR_NONE == json_last_error()) {
        // For file in a repo
        if ($is_repo) {
            if (isset($github_data['content'])) {
                $status = 'OK';
                $result = htmlspecialchars( base64_decode( $github_data['content'] ) );
            } else {
                $status = 'ERR';
                $result = 'Invalid repo file: ¿content? %s, <a href="https://github.com/¿user?">Repos</a>';
                $result = sprintf($result, '(' . $github_data['message'] . ')');
            }
        } elseif (!empty($github_data['files'])) { // For file in a Gists
            if ($file_name) {
                 // Remove simple/double quote from filename attribute
                $file_name = str_replace(['&quot;', '&#34;', '"', '&apos;', '&#039;', "'"], '', $file_name);
                if (isset($github_data['files'][$file_name])) {
                    $status = 'OK';
                    $result = htmlspecialchars( $github_data['files'][$file_name]['content'] );
                } else {
                    $status = 'ERR';
                    $result = 'Invalid file name: %s, <a href="https://gist.github.com/¿user?/¿content?">Gist</a>';
                    $result = sprintf($result, $file_name);
                }
            } else {
                $status = 'OK';
                $result = htmlspecialchars( reset( $github_data['files'] )['content'] );
            }
        } else {
            $status = 'ERR';
            $result = 'Invalid Gist: ¿content? %s, <a href="https://gist.github.com/¿user?">Gists</a>';
            $result = sprintf($result, '('. $github_data['message'] . ')');
        }
    } else {
        $status = 'ERR';
        $result = json_last_error_msg();
    }

    return array(
        'html_url' => isset($github_data['html_url']) ? $github_data['html_url'] : '',
        'status'   => $status,
        'content'   => $result
    );
}

function make_the_container( $container ) {
    $tags = explode('.', $container);
    $container = [];
    foreach ($tags as $tag) {
        $classes = '';
        $style = '';
        if (preg_match_all("/\(((\w+[-_\s]?)+)\)|\{((\s*(\w+-?\s*)+\s*:\s*(\w+-?\s*)+;?)+)\s*\}/", $tag, $matches)) {
            $tag = str_replace($matches[0], '', $tag);
            if (!empty($matches[1][0])) {
                $classes = sprintf(' class="%s"', $matches[1][0]);
            } elseif (!empty($matches[1][1])) {
                $classes = sprintf(' class="%s"', $matches[1][1]);
            } else {
                $classes = '';
            }

            if (!empty($matches[3][0])) {
                $style = sprintf(' style="%s"', $matches[3][0]);
            } else {
                $style = '';
            }
        }

        $container['start_tags'][] = "<$tag$classes$style>";
        $container['end_tags'][] = "</$tag>";
    }

    return implode('',
        array(
            implode( '', $container['start_tags'] ),
            '%s',
            implode( '', array_reverse( $container['end_tags'] ) )
        )
    );
}

function make_the_ribbon( $url, $ribbon_title = '' ) {
    return sprintf(
        '<a target="_blank" class="ghget-ribbon" href="%s">%s %s</a>%s',
        $url,
        '<span class="icon-code-fork"></span>',
        empty($ribbon_title) ? 'Fork me on Github' : $ribbon_title,
        PHP_EOL
    );
}


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
        'filename'     => '',
        'repo'         => false,
        'ribbon'       => true,
        'ribbontitle'  => '',
        'container'    => '',
        'account'      => '',
    ), $atts);

    $github_user = empty($args['account']) ? githubget_get_option('github_user') : $args['account'];
    $is_repo = strtolower($args['repo']);
    $is_repo = '1' == $is_repo || 'true' == $is_repo ? true: false;
    $has_ribbon =  '1' == $args['ribbon'] || 'true' == $args['ribbon'] ? true: false;
    $caches_the_content = false;

    // Content has been cached?
    $content_key = 'ghget_' . "{$github_user}_" . md5( md5( $args['filename'] ) . $content );

    // Avoid to make multiple request for Gist with multiple files
    $body_response_key = 'ghget_' . "{$github_user}_" . md5( $content );
    if ($body = get_transient( $body_response_key )) {
        $github_data = process_response_body( $body, $args['filename'], $is_repo );
        $result = $github_data['content'] . '***********FROMDB************';
        $has_ribbon = 'OK' == $github_data['status'] && $has_ribbon;
        $caches_the_content = 'OK' == $github_data['status'];
    } else {
        $github_token = githubget_get_option('github_token');
        $resource = get_resource( $github_user, $content, $is_repo );
        $reqargs['headers']['Authorization'] = 'token ' . $github_token;

        $github_data = get_transient( $content_key );
        if ( !empty( $github_data ) ) {
            $reqargs['headers']['If-Modified-Since'] = ' ' . gmdate('D, d M Y H:i:s T');
        }

        $response = wp_remote_get( $resource, $reqargs );
        $http_code = wp_remote_retrieve_response_code( $response );

        // Content no modified
        if (304 == $http_code) {
            $result =  $github_data['content'];
            $has_ribbon = false;
            $args['container'] = '';
            // Grab URL and pass it to the browser
        } elseif ($body = wp_remote_retrieve_body( $response )) {
            $github_data = process_response_body( $body, $args['filename'], $is_repo );

            $result = $github_data['content'];
            if ('OK' == $github_data['status']) {
                // Caches the full reponse, expires in 5 min this is useful to avoid to do the same
                // request to GitHub API for every file in gist with multiple file
                set_transient( $body_response_key, $body, 300 );
                $caches_the_content = true;
            } else {
                $has_ribbon = false;
                $result = str_replace(array('¿content?', '¿user?'), array($content, $github_user), $result);
            }
        } else {
            $result = 'Content can not be get from ' . $resource . '<br/>Response status: ' . $http_code;
            $has_ribbon = false;
        }
    }

    if (!empty($args['container'])) {
        $result = sprintf( make_the_container( $args['container'] ), $result );
    }

    if ($has_ribbon) {
        $result = make_the_ribbon( $github_data['html_url'], $args['ribbontitle'] ) . $result;
    }

    if ($caches_the_content) {
        // Cache only the content for specific file
        set_transient( $content_key, array('html_url' => $github_data['html_url'], 'content' =>  $result) );
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
