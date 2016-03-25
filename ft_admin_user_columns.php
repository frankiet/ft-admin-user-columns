<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
 /**********************************************************************
 *  Plugin Name: Admin User Columns
 *  Description: Display author's comment count. Sort the users list by comment count
 *  Version: 1.0
 *  Author: Frankie T
 *  License: GPLv2
 * ********************************************************************/

if ( ! class_exists( 'FT_Admin_User_Columns' ) ) :
	class FT_Admin_User_Columns {

		public function __construct(){
			add_filter( 'manage_users_columns',array($this, 'manage_users_columns') );
			add_filter( 'manage_users_custom_column', array($this, 'manage_users_custom_column'), 10, 3 );
			add_filter( 'manage_users_sortable_columns',array($this, 'manage_users_sortable_columns') );
			add_filter( 'pre_user_query',array($this, 'pre_user_query') );
		}

		/**
		 * Adds a column for the count of user objects.
		 *
		 * @since 1.0
		 *
		 * @param  array $users_columns Array of user column titles.
		 *
		 * @return array The $posts_columns array with the user comments count column's title added.
		 */
		public static function manage_users_columns( $columns ) {
			$columns['user_comments_count'] = __( 'Comments', 'text-domain' );
			return $columns;
		}

		/**
		 * Make comments-column sortable
		 *
		 * @since 1.0
		 */
		function manage_users_sortable_columns( $columns ){
			$columns['user_comments_count'] = 'user_comments_count';
			return $columns;
		}

		/**
		 * Fires after the WP_User_Query has been parsed, and before
		 * the query is executed.
		 *
		 * The passed WP_User_Query object contains SQL parts formed
		 * from parsing the given query.
		 *
		 * @since 1.0
		 *
		 * @param WP_User_Query $this The current WP_User_Query instance,
		 *                            passed by reference.
		 */
		function pre_user_query($userquery){
			global $wpdb;
			$order = isset($userquery->query_vars["order"]) ? $userquery->query_vars["order"] : '';
			$order = ($order && strtoupper($order) == 'ASC') ? 'ASC' : 'DESC';
			$orderby = isset($userquery->query_vars["orderby"]) ? $userquery->query_vars["orderby"] : '';
		 	if ( 'user_comments_count' == $orderby ) {
		 		$userquery->query_from .= 
				"
				LEFT OUTER JOIN (
				SELECT user_id, COUNT(*) as comments_count
				FROM $wpdb->comments
				GROUP BY user_id
				) comments ON ({$wpdb->users}.ID = comments.user_id)
				";
			 	$userquery->query_orderby = " ORDER BY comments_count {$order}";
		 	}

		 	return $userquery;
		}

		/**
		 * Outputs a linked count of the user's comments.
		 *
		 * @since 1.0
		 *
		 * @param string $output      Custom column output. Default empty.
		 * @param string $column_name Column name.
		 * @param int    $user_id     ID of the currently-listed user.
		 * @return string
		 */
		public function manage_users_custom_column( $output, $column_name, $user_id ) {
			if ( 'user_comments_count' == $column_name ) {
				$count = self::get_comment_count( $user_id );
				$url = add_query_arg(array('user_id' => $user_id ), admin_url('edit-comments.php'));
				$txt = sprintf( _n( '%d comment', '%d comments', $count, 'text-domain' ), $count );
				$o = "<a target='_blank' href='" . esc_url($url) . "' title='" . esc_attr( $txt ) . "'>";
				$o .= intval($count['all']);
				$o .= '</a>';
				return $o;
			}
		}

		/**
		 * Retrieve the total number of comments associated to blog users.
		 *
		 * @since 1.0
		 *
		 * @global wpdb $wpdb WordPress database abstraction object.
		 *
		 * @param int|string $id_or_email user_id or comment_author_email
		 * @return array
		 */
		public function get_comment_count( $id_or_email = '' ) {
			global $wpdb;

			$where = '';
			if( is_numeric($id_or_email) && $id_or_email>0 ){
				if( !$user = get_user_by('id',$id_or_email) )
					return 0;

				$where = $wpdb->prepare("WHERE user_id = %d", $id_or_email);
			}elseif( is_email($id_or_email) ){
				if( !$user = get_user_by('email',$id_or_email) )
					return 0;
				
				$where = $wpdb->prepare("WHERE comment_author_email = %s", $id_or_email);
			}else{
				return 0;
			}

			$totals = (array) $wpdb->get_results("
				SELECT comment_approved, COUNT( * ) AS total
				FROM {$wpdb->comments}
				{$where}
				GROUP BY comment_approved
			", ARRAY_A);

			$comment_count = array(
				'approved'            => 0,
				'awaiting_moderation' => 0,
				'spam'                => 0,
				'trash'               => 0,
				'post-trashed'        => 0,
				'total_comments'      => 0,
				'all'                 => 0,
			);

			foreach ( $totals as $row ) {
				switch ( $row['comment_approved'] ) {
					case 'trash':
						$comment_count['trash'] = $row['total'];
						break;
					case 'post-trashed':
						$comment_count['post-trashed'] = $row['total'];
						break;
					case 'spam':
						$comment_count['spam'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						break;
					case '1':
						$comment_count['approved'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						$comment_count['all'] += $row['total'];
						break;
					case '0':
						$comment_count['awaiting_moderation'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						$comment_count['all'] += $row['total'];
						break;
					default:
						break;
				}
			}

			return $comment_count;
		}

	}

	add_action('admin_init','ft_admin_user_columns');
	function ft_admin_user_columns() {
		if( is_admin() && current_user_can('list_users' ) ) {
			new FT_Admin_User_Columns();
		}
	}
	
endif; // end if !class_exists()
