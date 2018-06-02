<?php
/**
 * Plugin Name: Comments on AMP pages
 * Plugin URI: https://www.algarrobonoticias.es
 * Description: Allows for comments on AMP pages.
 * Version: 1.0.0
 * Author: Jordi Ramos
 * Author URI: https://www.algarrobonoticias.es
 * License: GPL2
 */

/**
 * Allows for comments on AMP pages.
 * It requires the use of:
 *   <script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>
 *   <script async custom-template="amp-mustache" src="https://cdn.ampproject.org/v0/amp-mustache-0.1.js"></script>

 * Customize success or error messages by using `amp-form-submit-success` and `amp-form-submit-error` classes.
 *    <style amp-custom>
 *      form.amp-form-submit-success [submit-success],
 *      form.amp-form-submit-error [submit-error]{
 *          margin-top: 16px;
 *      }
 *      form.amp-form-submit-success [submit-success] {
 *          color: green;
 *      }
 *      form.amp-form-submit-error [submit-error] {
 *          color: red;
 *      }
 *    </style>

 * Usage, call the function:
 *    comment_form_amp();
 
 * Limitations:
 *    It does not allow the use of nested comments.
*/

// exit if accessed directly
if (!defined('ABSPATH')) exit;


function comment_enviar()
{
	if (!empty($_POST)) {
		$name = $_POST['author'];
		$domain_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
		$commentdata = array(
			'comment_post_ID' => $_POST['comment_post_ID'], // to which post the comment will show up
			'comment_author' => $_POST['author'], //fixed value - can be dynamic
			'comment_author_email' => $_POST['email'], //fixed value - can be dynamic
			'comment_author_url' => $_POST['url'], //fixed value - can be dynamic
			'comment_content' => $_POST['comment'], //fixed value - can be dynamic
			'comment_type' => '', //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
			'comment_parent' => 0, //0 if it's not a reply to another comment; if it's a reply, mention the parent comment ID here
			'user_id' => get_current_user_id() //passing current user ID or any predefined as per the demand
		);
		$allowed = wp_allow_comment($commentdata, true);
		header("Content-type: application/json");
		header("Access-Control-Expose-Headers: AMP-Redirect-To, AMP-Access-Control-Allow-Source-Origin");
		header("AMP-Access-Control-Allow-Source-Origin: " . $domain_url);
		switch ($allowed) {
		case '0':
			$msg = 'Done! Thank you ' . $name . '. Your comment is awaiting approval. Come back later to see it.';
			$comment_id = wp_new_comment($commentdata);
			break;

		case '1':

//			header('AMP-Redirect-To: '. get_permalink($_POST['comment_post_ID']) .'#comment-' .$comment_ID );
			header('AMP-Redirect-To: ' . get_permalink($_POST['comment_post_ID']));
			$msg = 'Done! Thank you ' . $name . ' for your comment. You can see it at the bottom of the page when it reloads';
			$comment_id = wp_new_comment($commentdata);
			break;

		case 'spam':
			$msg = '';
			$comment_id = wp_new_comment($commentdata);
			return;
			break;

		default:
			if (is_wp_error($allowed)) {
				$data = intval($allowed->get_error_data());
				if (!empty($data)) {
					status_header(400);
					wp_send_json(array(
						'success' => false,
						'msg' => $allowed->get_error_message() ,
						'response' => $data,
						'back_link' => true
					));
					return;
				}
			}
		}

		if (is_wp_error($comment_id)) {
			$data = intval($comment_id->get_error_data());
			if (!empty($data)) {
				status_header(400);
				wp_send_json(array(
					'success' => false,
					'msg' => $comment_id->get_error_message()
				));
				return;
			}
		}

		wp_send_json(array(
			'success' => true,
			'msg' => $msg
		));
		return;
	}
}

add_action('wp_ajax_comment_enviar', 'comment_enviar');
add_action('wp_ajax_nopriv_comment_enviar', 'comment_enviar');


/*----------------- This is a modified version of the comment_form() function found at wp-includes/comment-template.php. -------------------*/ 
/* ---------------------------------------------------------------------------------------------------------------------------------------- */
/**
 * Outputs a complete commenting form for use within a template.
 *
 * Most strings and form fields may be controlled through the $args array passed
 * into the function, while you may also choose to use the {@see 'comment_form_default_fields'}

 * filter to modify the array of default fields if you'd just like to add a new
 * one or remove a single field. All fields are also individually passed through
 * a filter of the {@see 'comment_form_field_$name'} where $name is the key used
 * in the array of fields.
 *
 * @since 3.0.0
 * @since 4.1.0 Introduced the 'class_submit' argument.
 * @since 4.2.0 Introduced the 'submit_button' and 'submit_fields' arguments.
 * @since 4.4.0 Introduced the 'class_form', 'title_reply_before', 'title_reply_after',
 *              'cancel_reply_before', and 'cancel_reply_after' arguments.
 * @since 4.5.0 The 'author', 'email', and 'url' form fields are limited to 245, 100,
 *              and 200 characters, respectively.
 * @since 4.6.0 Introduced the 'action' argument.
 *
 * @param array       $args {
 *     Optional. Default arguments and form fields to override.
 *
 *     @type array $fields {
 *         Default comment fields, filterable by default via the {@see 'comment_form_default_fields'} hook.
 *
 *         @type string $author Comment author field HTML.
 *         @type string $email  Comment author email field HTML.
 *         @type string $url    Comment author URL field HTML.
 *     }

 *     @type string $comment_field        The comment textarea field HTML.
 *     @type string $must_log_in          HTML element for a 'must be logged in to comment' message.
 *     @type string $logged_in_as         HTML element for a 'logged in as [user]' message.
 *     @type string $comment_notes_before HTML element for a message displayed before the comment fields
 *                                        if the user is not logged in.
 *                                        Default 'Your email address will not be published.'.
 *     @type string $comment_notes_after  HTML element for a message displayed after the textarea field.
 *     @type string $action               The comment form element action attribute. Default '/wp-comments-post.php'.
 *     @type string $id_form              The comment form element id attribute. Default 'commentform'.
 *     @type string $id_submit            The comment submit element id attribute. Default 'submit'.
 *     @type string $class_form           The comment form element class attribute. Default 'comment-form'.
 *     @type string $class_submit         The comment submit element class attribute. Default 'submit'.
 *     @type string $name_submit          The comment submit element name attribute. Default 'submit'.
 *     @type string $title_reply          The translatable 'reply' button label. Default 'Leave a Reply'.
 *     @type string $title_reply_to       The translatable 'reply-to' button label. Default 'Leave a Reply to %s',
 *                                        where %s is the author of the comment being replied to.
 *     @type string $title_reply_before   HTML displayed before the comment form title.
 *                                        Default: '<h3 id="reply-title" class="comment-reply-title">'.
 *     @type string $title_reply_after    HTML displayed after the comment form title.
 *                                        Default: '</h3>'.
 *     @type string $cancel_reply_before  HTML displayed before the cancel reply link.
 *     @type string $cancel_reply_after   HTML displayed after the cancel reply link.
 *     @type string $cancel_reply_link    The translatable 'cancel reply' button label. Default 'Cancel reply'.
 *     @type string $label_submit         The translatable 'submit' button label. Default 'Post a comment'.
 *     @type string $submit_button        HTML format for the Submit button.
 *                                        Default: '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />'.
 *     @type string $submit_field         HTML format for the markup surrounding the Submit button and comment hidden
 *                                        fields. Default: '<p class="form-submit">%1$s %2$s</p>', where %1$s is the
 *                                        submit button markup and %2$s is the comment hidden fields.
 *     @type string $format               The comment form format. Default 'xhtml'. Accepts 'xhtml', 'html5'.
 * }

 * @param int|WP_Post $post_id Post ID or WP_Post object to generate the form for. Default current post.
 */

function comment_form_amp($args = array() , $post_id = null)
{
	if (null === $post_id) $post_id = get_the_ID();

	// Exit the function when comments for the post are closed.

	if (!comments_open($post_id)) {
		/**
		 * Fires after the comment form if comments are closed.
		 *
		 * @since 3.0.0
		 */
		do_action('comment_form_comments_closed');
		return;
	}

	$commenter = wp_get_current_commenter();
	$user = wp_get_current_user();
	$user_identity = $user->exists() ? $user->display_name : '';
	$args = wp_parse_args($args);
	if (!isset($args['format'])) $args['format'] = current_theme_supports('html5', 'comment-form') ? 'html5' : 'xhtml';
	$req = get_option('require_name_email');
	$html_req = ($req ? " required='required'" : '');
	$html5 = 'html5' === $args['format'];
	$consent = empty($commenter['comment_author_email']) ? '' : ' checked="checked"';
	$fields = array(
		'author' => '<p class="comment-form-author">' . '<label for="author">' . __('Name') . ($req ? ' <span class="required">*</span>' : '') . '</label> ' . '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" maxlength="245" placeholder="' . __('Name', 'generatepress') . '" required /></p>',
		'email' => '<p class="comment-form-email"><label for="email">' . __('Email') . ($req ? ' <span class="required">*</span>' : '') . '</label> ' . '<input id="email" name="email" type="email" value="" maxlength="100"  placeholder="' . __('Email', 'generatepress') . '" required  /></p>',
		'url' => '<p class="comment-form-url"><label for="url">' . __('Website') . '</label> ' . '<input placeholder="' . __('Website', 'generatepress') . '" id="url" name="url" value="" size="30" type="url" maxlength="200" /></p>',
		'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' />' . '<label for="wp-comment-cookies-consent"> ' . __('Save my name, email, and website in this browser for the next time I comment.') . '</label></p>',
	);
	$required_text = sprintf(' ' . __('Required fields are marked %s') , '<span class="required">*</span>');
	/**
	 * Filters the default comment form fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $fields The default comment fields.
	 */
	$fields = apply_filters('comment_form_default_fields', $fields);
	$defaults = array(
		'fields' => $fields,
		'comment_field' => '<p class="comment-form-comment"><label for="comment">' . _x('Comment', 'noun') . '*</label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" aria-required="true" required></textarea></p>',
		/** This filter is documented in wp-includes/link-template.php */
		'must_log_in' => '<p class="must-log-in">' . sprintf(
		/* translators: %s: login URL */
		__('You must be <a href="%s">logged in</a> to post a comment.') , wp_login_url(apply_filters('the_permalink', get_permalink($post_id) , $post_id))) . '</p>',
		/** This filter is documented in wp-includes/link-template.php */
		'logged_in_as' => '<p class="logged-in-as">' . sprintf(
		/* translators: 1: edit user link, 2: accessibility text, 3: user name, 4: logout URL */
		__('<a href="%1$s" aria-label="%2$s">Logged in as %3$s</a>. <a href="%4$s">Log out?</a>') , get_edit_user_link() ,
		/* translators: %s: user name */
		esc_attr(sprintf(__('Logged in as %s. Edit your profile.') , $user_identity)) , $user_identity, wp_logout_url(apply_filters('the_permalink', get_permalink($post_id) , $post_id))) . '</p>',
		'comment_notes_before' => '<p class="comment-notes"><span id="email-notes">' . __('Your email address will not be published.') . '</span>' . ($req ? $required_text : '') . '</p>',
		'comment_notes_after' => '',
		'action' => site_url('/wp-comments-post.php') ,
		'id_form' => 'commentform',
		'id_submit' => 'submit',
		'class_form' => 'comment-form',
		'class_submit' => 'submit',
		'name_submit' => 'submit',
		'title_reply' => __('Leave a Reply') ,
		'title_reply_to' => __('Leave a Reply to %s') ,
		'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
		'title_reply_after' => '</h3>',
		'cancel_reply_before' => ' <small>',
		'cancel_reply_after' => '</small>',
		'cancel_reply_link' => __('Cancel reply') ,
		'label_submit' => __('Post Comment') ,
		'submit_button' => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />',
		'submit_field' => '<p class="form-submit">%1$s %2$s</p>',
		'format' => 'xhtml',
	);
	/**
	 * Filters the comment form default arguments.
	 *
	 * Use {@see 'comment_form_default_fields'} to filter the comment fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $defaults The default comment form arguments.
	 */
	$args = wp_parse_args($args, apply_filters('comment_form_defaults', $defaults));

	// Ensure that the filtered args contain all required default values.

	$args = array_merge($defaults, $args);
	/**
	 * Fires before the comment form.
	 *
	 * @since 3.0.0
	 */
	do_action('comment_form_before');
?>
	<div id="respond" class="comment-respond">
		<?php
	echo $args['title_reply_before'];

	//		comment_form_title( $args['title_reply'], $args['title_reply_to'] );

	comment_form_title($args['title_reply']);

	//		echo $args['cancel_reply_before'];
	//		cancel_comment_reply_link( $args['cancel_reply_link'] );
	//		echo $args['cancel_reply_after'];

	echo $args['title_reply_after'];
	if (get_option('comment_registration') && !is_user_logged_in()):
		echo $args['must_log_in'];
		/**
		 * Fires after the HTML-formatted 'must log in after' message in the comment form.
		 *
		 * @since 3.0.0
		 */
		do_action('comment_form_must_log_in_after');
	else: ?>
			<form method="post" id="commentform" class="comment-form" action-xhr="<?php
		echo admin_url('admin-ajax.php?action=comment_enviar') ?>" target="_top">
				<?php
		/**
		 * Fires at the top of the comment form, inside the form tag.
		 *
		 * @since 3.0.0
		 */
		do_action('comment_form_top');
		if (is_user_logged_in()):
			/**
			 * Filters the 'logged in' message for the comment form for display.
			 *
			 * @since 3.0.0
			 *
			 * @param string $args_logged_in The logged-in-as HTML-formatted message.
			 * @param array  $commenter      An array containing the comment author's
			 *                               username, email, and URL.
			 * @param string $user_identity  If the commenter is a registered user,
			 *                               the display name, blank otherwise.
			 */
			echo apply_filters('comment_form_logged_in', $args['logged_in_as'], $commenter, $user_identity);
			/**
			 * Fires after the is_user_logged_in() check in the comment form.
			 *
			 * @since 3.0.0
			 *
			 * @param array  $commenter     An array containing the comment author's
			 *                              username, email, and URL.
			 * @param string $user_identity If the commenter is a registered user,
			 *                              the display name, blank otherwise.
			 */
			do_action('comment_form_logged_in_after', $commenter, $user_identity);
		else:
			echo $args['comment_notes_before'];
		endif;

		// Prepare an array of all fields, including the textarea

		$comment_fields = array(
			'comment' => $args['comment_field']
		) + (array)$args['fields'];
		/**
		 * Filters the comment form fields, including the textarea.
		 *
		 * @since 4.4.0
		 *
		 * @param array $comment_fields The comment fields.
		 */
		$comment_fields = apply_filters('comment_form_fields', $comment_fields);

		// Get an array of field names, excluding the textarea

		$comment_field_keys = array_diff(array_keys($comment_fields) , array(
			'comment'
		));

		// Get the first and the last field name, excluding the textarea

		$first_field = reset($comment_field_keys);
		$last_field = end($comment_field_keys);
		foreach($comment_fields as $name => $field) {
			if ('comment' === $name) {
				/**
				 * Filters the content of the comment textarea field for display.
				 *
				 * @since 3.0.0
				 *
				 * @param string $args_comment_field The content of the comment textarea field.
				 */
				echo apply_filters('comment_form_field_comment', $field);
				echo $args['comment_notes_after'];
			}
			elseif (!is_user_logged_in()) {
				if ($first_field === $name) {
					/**
					 * Fires before the comment fields in the comment form, excluding the textarea.
					 *
					 * @since 3.0.0
					 */
					do_action('comment_form_before_fields');
				}

				/**
				 * Filters a comment form field for display.
				 *
				 * The dynamic portion of the filter hook, `$name`, refers to the name
				 * of the comment form field. Such as 'author', 'email', or 'url'.
				 *
				 * @since 3.0.0
				 *
				 * @param string $field The HTML-formatted output of the comment form field.
				 */
				echo apply_filters("comment_form_field_{$name}", $field) . "\n";
				if ($last_field === $name) {
					/**
					 * Fires after the comment fields in the comment form, excluding the textarea.
					 *
					 * @since 3.0.0
					 */
					do_action('comment_form_after_fields');
				}
			}
		}

		$submit_button = sprintf($args['submit_button'], esc_attr($args['name_submit']) , esc_attr($args['id_submit']) , esc_attr($args['class_submit']) , esc_attr($args['label_submit']));
		/**
		 * Filters the submit button for the comment form to display.
		 *
		 * @since 4.2.0
		 *
		 * @param string $submit_button HTML markup for the submit button.
		 * @param array  $args          Arguments passed to `comment_form()`.
		 */
		$submit_button = apply_filters('comment_form_submit_button', $submit_button, $args);
		$submit_field = sprintf($args['submit_field'], $submit_button, get_comment_id_fields($post_id));
		/**
		 * Filters the submit field for the comment form to display.
		 *
		 * The submit field includes the submit button, hidden fields for the
		 * comment form, and any wrapper markup.
		 *
		 * @since 4.2.0
		 *
		 * @param string $submit_field HTML markup for the submit field.
		 * @param array  $args         Arguments passed to comment_form().
		 */
		echo apply_filters('comment_form_submit_field', $submit_field, $args);
		/**
		 * Fires at the bottom of the comment form, inside the closing </form> tag.
		 *
		 * @since 1.5.0
		 *
		 * @param int $post_id The post ID.
		 */

		//				do_action( 'comment_form', $post_id );

?>
	<div submit-success>
      <template type="amp-mustache">
         {{msg}} 
      </template>
    </div>
    <div submit-error>
      <template type="amp-mustache">
        {{msg}} 
      </template>
    </div>
			</form>
		<?php
	endif; ?>
	</div><!-- #respond -->
	<?php
	/**
	 * Fires after the comment form.
	 *
	 * @since 3.0.0
	 */
	do_action('comment_form_after');
}