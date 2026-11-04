<?php
/**
 * Default sitemap builder
 *
 * @package Sitemap
 * @author  Arne Brachhold
 * @since   4.0
 */
/**
 * Class
 */
class GoogleSitemapGeneratorStandardBuilder {

	private $linkPerPage = 1000;
	private $maxLinksPerPage = 50000;

	/**
	 * Creates a new GoogleSitemapGeneratorStandardBuilder instance
	 */
	public function __construct() {
		add_action( 'sm_build_index', array( $this, 'index' ), 10, 1 );
		add_action( 'sm_build_content', array( $this, 'content' ), 10, 3 );

		add_filter( 'sm_sitemap_for_post', array( $this, 'get_sitemap_url_for_post' ), 10, 3 );
	}

	/**
	 * Generates the content of the requested sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg instance of sitemap generator.
	 * @param String                 $type the type of the sitemap.
	 * @param String                 $params Parameters for the sitemap.
	 */
	public function content( $gsg, $type, $params ) {
		$params = strval($params);
		if (strpos($params, '/') !== false){
            $newType = explode('/', $params);
            $params = end($newType);
        }
		switch ( $type ) {
			case 'pt':
				$this->build_posts( $gsg, $params );
				break;
			case 'archives':
				$this->build_archives( $gsg );
				break;
			case 'authors':
				$this->build_authors( $gsg );
				break;
			case 'tax':
				$this->build_taxonomies( $gsg, $params );
				break;
			case 'producttags':
				$this->build_product_tags( $gsg, $params );
				break;
			case 'productcat':
				$this->build_product_categories( $gsg, $params );
				break;
			case 'externals':
				$this->build_externals( $gsg );
				break;
			case 'misc':
			default:
				$this->build_misc( $gsg );
				break;
		}
	}

	/**
	 * Generates the content for the post sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg instance of sitemap generator.
	 * @param string                 $params string.
	 */
	public function build_posts( $gsg, $params ) {

		$pts = strrpos( $params, '-' );

		if ( ! $pts ) {
			return;
		}

		$pts = strrpos( $params, '-', $pts - strlen( $params ) - 1 );

		$param_length   = count( explode( '-', $params ) );
		$post_type = '';
		$post_type      = substr( $params, 0, $pts );
		$type           = explode( '-', $post_type );
		if ( $param_length > 4 ) {
			$new = array_slice( $type,0,count($type) -1 );
			$post_type = implode( "-", $new );
		} else	{
			$post_type  = $type[0];
		}
		$limit          = $type[count($type)-1];
		$limits         = substr( $limit, 1 );
		$links_per_page = $gsg->get_entries_per_page();
		if ( gettype( $links_per_page ) !== 'integer' ) {
			$links_per_page = (int) 1000;
		}
		$limit          = ( (int) $limits ) * $links_per_page;
		if ( ! $post_type || ! in_array( $post_type, $gsg->get_active_post_types(), true ) ) {
			return;
		}

			$params = substr( $params, $pts + 1 );

			/**
			 * Global variable for database.
			 *
			 * @var $wpdb wpdb
			 */
			global $wpdb;

		if ( preg_match( '/^([0-9]{4})\-([0-9]{2})$/', $params, $matches ) ) {
			$year  = $matches[1];
			$month = $matches[2];

			// Excluded posts by ID.
			$excluded_post_ids = $gsg->get_excluded_post_ids( $gsg );
			$not_allowed_slugs = $gsg->robots_disallowed();
			$excluded_post_ids = array_unique( array_merge( $excluded_post_ids, $not_allowed_slugs ), SORT_REGULAR );
			$gsg->set_option( 'b_exclude', $excluded_post_ids );
			$gsg->save_options();
			$ex_post_s_q_l = '';
			if ( count( $excluded_post_ids ) > 0 ) {
				$ex_post_s_q_l = 'AND p.ID NOT IN (' . implode( ',', $excluded_post_ids ) . ')';
			}

			// Excluded categories by taxonomy ID.
			$excluded_category_i_d_s = $gsg->get_excluded_category_i_ds( $gsg );
			$ex_cat_s_q_l            = '';
			if ( count( $excluded_category_i_d_s ) > 0 ) {
				$ex_cat_s_q_l = "AND ( p.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ( " . implode( ',', $excluded_category_i_d_s ) . '))))';
			}
			// Statement to query the actual posts for this post type.
			$qs = "
				SELECT
					p.ID,
					p.post_author,
					p.post_status,
					p.post_name,
					p.post_parent,
					p.post_type,
					p.post_date,
					p.post_date_gmt,
					p.post_modified,
					p.post_modified_gmt,
					p.comment_count
				FROM
					{$wpdb->posts} p
				WHERE
					p.post_password = ''
					AND p.post_type = '%s'
					AND p.post_status = 'publish'
					{$ex_post_s_q_l}
					{$ex_cat_s_q_l}
				ORDER BY
					p.post_date_gmt ASC
				LIMIT
					%d, %d
			";
			// Query for counting all relevant posts for this post type.
			$qsc = "
				SELECT
					COUNT(*)
				FROM
					{$wpdb->posts} p
				WHERE
					p.post_password = ''
					AND p.post_type = '%s'
					AND p.post_status = 'publish'
					{$ex_post_s_q_l}
					{$ex_cat_s_q_l}
			";

			// Calculate the offset based on the limit and links_per_page
			$offset = max( 0, ( $limit - $links_per_page ) );

			// phpcs:disable
			$q = $wpdb->prepare( $qs, $post_type, $offset, $links_per_page );

			// phpcs:enable
			$posts      = $wpdb->get_results( $q ); // phpcs:ignore
			$post_count = count( $posts );
			if ( ( $post_count ) > 0 ) {
				/**
				 * Description for priority provider
				 *
				 * @var $priority_provider GoogleSitemapGeneratorPrioProviderBase
				 */
				$priority_provider = null;

				if ( $gsg->get_option( 'b_prio_provider' ) !== '' ) {

					// Number of comments for all posts.
					$cache_key     = __CLASS__ . '::commentCount';
					$comment_count = wp_cache_get( $cache_key, 'sitemap' );
					if ( false === $comment_count ) {
						$comment_count = $wpdb->get_var( "SELECT COUNT(*) as `comment_count` FROM {$wpdb->comments} WHERE `comment_approved`='1'" );  // db call ok.
						wp_cache_set( $cache_key, $comment_count, 'sitemap', 20 );
					}

					// Number of all posts matching our criteria.
					$cache_key        = __CLASS__ . "::totalPostCount::$post_type";
					$total_post_count = wp_cache_get( $cache_key, 'sitemap' );
					if ( false === $total_post_count ) {
						// phpcs:disable
						$total_post_count = $wpdb->get_var(
							$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_password = '' AND p.post_type = '%s' AND p.post_status = 'publish' " . $ex_post_s_q_l . " " . $ex_cat_s_q_l . " ",  $post_type ) // phpcs:ignore
						); // db call ok.
						// phpcs:enable
						wp_cache_add( $cache_key, $total_post_count, 'sitemap', 20 );
					}

					// Initialize a new priority provider.
					$provider_class    = $gsg->get_option( 'b_prio_provider' );
					$priority_provider = new $provider_class( $comment_count, $total_post_count );
				}

				// Default priorities.
				$default_priority_for_posts = $gsg->get_option( 'pr_posts' );
				$default_priority_for_pages = $gsg->get_option( 'pr_pages' );

				// Minimum priority.
				$minimum_priority = $gsg->get_option( 'pr_posts_min' );

				// Change frequencies.
				$change_frequency_for_posts = $gsg->get_option( 'cf_posts' );
				$change_frequency_for_pages = $gsg->get_option( 'cf_pages' );

				// Page as home handling.
				$home_pid = 0;
				$home     = get_home_url();
				if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
					$page_on_front = get_option( 'page_on_front' );
					$p             = get_post( $page_on_front );
					if ( $p ) {
						$home_pid = $p->ID;
					}
				}

				$siteLanguages = [];
				$defaultLanguageCode = '';

				if (function_exists('icl_get_languages')) {
					if (function_exists('icl_get_default_language')) $defaultLanguageCode = icl_get_default_language();
					$languages = icl_get_languages('skip_missing=0');
					if($languages){
						foreach ($languages as $language) {
							if($defaultLanguageCode !== $language['language_code']) $siteLanguages[] = $language['language_code'];
						}
					}
				} else if (function_exists('pll_the_languages')) {
					if (function_exists('pll_default_language')) $defaultLanguageCode = pll_default_language();
					$languages = pll_the_languages(array('raw' => 1));
					if ($languages) {
						foreach ($languages as $language) {
							if($defaultLanguageCode !== $language['slug']) $siteLanguages[] = $language['slug'];
						}
					}
				}

				foreach ( $posts as $post ) {

					$permalink = get_permalink( $post );

					$permalink = apply_filters( 'sm_xml_sitemap_post_url', $permalink, $post );

					if(count($siteLanguages) > 0){

						$structurekArr = explode('/', get_option('permalink_structure'));
						$postLinkArr = explode('/', $permalink);

						$index = null;
						if(is_array($structurekArr) && is_array($postLinkArr)){
							foreach ($siteLanguages as $lang){
								if (in_array($lang, $postLinkArr)) {
									$index = array_search($lang, $postLinkArr);
								}
							}
						}

                        if ($index !== null) {
                            if ($postLinkArr[$index] !== $defaultLanguageCode) {
                                $custom_post_type_name = get_post_type($post);
                                if (!in_array($custom_post_type_name, $postLinkArr)) {
                                    $key = array_search('%postname%', $structurekArr);
                                    if ($structurekArr[$key] === '%postname%') {
                                        $postLinkArr[$index + $key] = $post->post_name;
                                    }
                                }
                                $permalink = implode('/', $postLinkArr);
                            }
                        }
					}

					// Exclude the home page and placeholder items by some plugins. Also include only internal links.
					if (
						( ! empty( $permalink ) )
						&& $permalink !== $home
						&& $post->ID !== $home_pid
						&& strpos( $permalink, $home ) !== false
					) {

						// Default Priority if auto calc is disabled.
						$priority = ( 'page' === $post_type ? $default_priority_for_pages : $default_priority_for_posts );

						// If priority calc. is enabled, calculate (but only for posts, not pages)!
						if ( null !== $priority_provider && 'post' === $post_type ) {
							$priority = $priority_provider->get_post_priority( $post->ID, $post->comment_count, $post );
						}

						// Ensure the minimum priority.
						if ( 'post' === $post_type && $minimum_priority > 0 && $priority < $minimum_priority ) {
							$priority = $minimum_priority;
						}

						// Both GMT columns are checked against the zero-date
						// sentinel. get_timestamp_from_my_sql() splits
						// '0000-00-00 00:00:00' without complaint and mktime()
						// turns it into 1999-11-30, which the clamp cannot
						// catch because it is in the past — the post would
						// simply advertise a wrong date forever.
						$post_gmt = '';
						if ( ! empty( $post->post_modified_gmt ) && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
							$post_gmt = $post->post_modified_gmt;
						} elseif ( ! empty( $post->post_date_gmt ) && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
							$post_gmt = $post->post_date_gmt;
						}
						// Add the URL to the sitemap.
						$gsg->add_url(
							$permalink,
							( '' !== $post_gmt ? $gsg->get_timestamp_from_my_sql( $post_gmt ) : 0 ),
							( 'page' === $post_type ? $change_frequency_for_pages : $change_frequency_for_posts ),
							$priority,
							$post->ID
						);
					}

					// Why not use clean_post_cache? Because some plugin will go crazy then (lots of database queries).
					// The post cache was not populated in a clean way, so we also won't delete it using the API.
					// wp_cache_delete( $post->ID, 'posts' );.
					unset( $post );
				}
				unset( $posts_custom );
			}
		}
	}


	/**
	 * Generates the content for the archives sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg object of google sitemap.
	 */
	public function build_archives( $gsg ) {
		/**
		 * Super global variable for database.
		 *
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		$now = current_time( 'mysql', true );

		$archives = $wpdb->get_results(
			// phpcs:disable
			$wpdb->prepare(
				"SELECT DISTINCT
					YEAR(post_date_gmt) AS `year`,
					MONTH(post_date_gmt) AS `month`,
					MAX(post_date_gmt) AS last_mod,
					count(ID) AS posts
				FROM
					$wpdb->posts
				WHERE
					post_date_gmt < '%s'
					AND post_status = 'publish'
					AND post_type = 'post'
				GROUP BY
					YEAR(post_date_gmt),
					MONTH(post_date_gmt)
				ORDER BY
				post_date_gmt DESC",
				$now
			)
			// phpcs:enable
		); // db call ok; no-cache ok.

		if ( $archives ) {
			foreach ( $archives as $archive ) {

				$url = get_month_link( $archive->year, $archive->month );

				// Archive is the current one.
				if ( gmdate( 'n' ) === $archive->month && gmdate( 'Y' ) === $archive->year ) {
					$change_freq = $gsg->get_option( 'cf_arch_curr' );
				} else { // Archive is older.
					$change_freq = $gsg->get_option( 'cf_arch_old' );
				}

				$gsg->add_url( $url, $gsg->get_timestamp_from_my_sql( $archive->last_mod ), $change_freq, $gsg->get_option( 'pr_arch' ) );
			}
		}

		$post_type_customs = get_post_types( array( 'public' => 1 ) );
		$post_type_customs = array_diff( $post_type_customs, array( 'page', 'attachment', 'product', 'post' ) );
		foreach ( $post_type_customs as $post_type_custom ) {
			$latest = new WP_Query(
				array(
					'post_type'      => $post_type_custom,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);

			if ( $latest->have_posts() ) {
				// post_modified_gmt, because get_timestamp_from_my_sql() builds
				// its timestamp with mktime() under WordPress's UTC default
				// timezone — handing it the site-local post_modified column
				// shifts the archive's lastmod by the site's UTC offset, and
				// on UTC+ sites into the future.
				//
				// When that column is zeroed the local one is CONVERTED
				// rather than simply relabelled as UTC. Appending '+0000' to
				// a local timestamp asserts something untrue and reproduces
				// the very offset this is fixing — which the clamp would
				// then turn into a silently missing element. With no
				// converter available the date is left unknown: an absent
				// lastmod is honest, a wrong one is not.
				$modified_date = ! empty( $latest->posts[0]->post_modified_gmt ) && '0000-00-00 00:00:00' !== $latest->posts[0]->post_modified_gmt
					? $latest->posts[0]->post_modified_gmt
					: ( function_exists( 'get_gmt_from_date' )
						&& ! empty( $latest->posts[0]->post_modified )
						&& '0000-00-00 00:00:00' !== $latest->posts[0]->post_modified
						? get_gmt_from_date( $latest->posts[0]->post_modified )
						: '' );
				// > 0, not merely truthy: get_gmt_from_date() on an unusable
				// date yields a year -0001 string rather than false, and the
				// resulting large NEGATIVE timestamp is truthy — enough to
				// pass a bare check and pick the wrong change frequency.
				$modified_ts = ! empty( $modified_date ) ? (int) strtotime( $modified_date . ' +0000' ) : 0;
				if ( $modified_ts < 0 ) {
					$modified_ts = 0;
				}
				if ( $modified_ts && gmdate( 'n', $modified_ts ) === gmdate( 'n' ) && gmdate( 'Y', $modified_ts ) === gmdate( 'Y' ) ) {
					$change_freq = $gsg->get_option( 'cf_arch_curr' );
				} else { // Archive is older.
					$change_freq = $gsg->get_option( 'cf_arch_old' );
				}
				// $modified_ts, not $modified_date: get_timestamp_from_my_sql()
				// explodes its argument on ' ', '-' and ':' with no guard, so
				// an empty string raises a run of undefined-offset notices and
				// returns a 1999 timestamp — advertising a wrong date instead
				// of omitting an unknown one.
				$gsg->add_url( get_post_type_archive_link( $post_type_custom ), $modified_ts, $change_freq, $gsg->get_option( 'pr_arch' ), 0, array(), array(), '' );
			}
		}
	}

	/**
	 * Generates the misc sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg instence of sitemap generator class.
	 */
	public function build_misc( $gsg ) {
		/*
		 * The same resolver index() uses, so one site-wide value cannot
		 * produce two different answers inside a single build. It already
		 * rejects the '0000-00-00 00:00:00' sentinel — which this function
		 * used to hand to get_timestamp_from_my_sql(), turning it into a
		 * 1999 date on the home page — and it applies the future-poison
		 * recovery, which this function did not have at all: on a site with
		 * one bogus future row the index entries were being rescued while
		 * the home page and the HTML sitemap silently lost their <lastmod>.
		 *
		 * It returns a UTC timestamp rather than a MySQL string, so the
		 * conversions below are gone. 0 means unknown, which both render
		 * sites already omit — never time(), which would move on every
		 * request and teach crawlers to ignore the field.
		 */
		$lm = self::resolve_blog_lastmod();

		if ( $gsg->get_option( 'in_home' ) ) {
			$home = get_bloginfo( 'url' );

			// Add the home page (WITH a slash!).
			if ( $gsg->get_option( 'in_home' ) ) {
				if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
					$page_on_front = get_option( 'page_on_front' );
					$p             = get_post( $page_on_front );
					if ( $p ) {
						// Both columns are checked against the zero-date
						// sentinel, not just the first. get_timestamp_from_my_sql()
						// splits '0000-00-00 00:00:00' happily and mktime()
						// turns it into 1999-11-30, so an unguarded fallback
						// ships that as the home page's <lastmod>. When
						// neither column holds a date the element is omitted.
						$front_gmt = '';
						if ( ! empty( $p->post_modified_gmt ) && '0000-00-00 00:00:00' !== $p->post_modified_gmt ) {
							$front_gmt = $p->post_modified_gmt;
						} elseif ( ! empty( $p->post_date_gmt ) && '0000-00-00 00:00:00' !== $p->post_date_gmt ) {
							$front_gmt = $p->post_date_gmt;
						}
						$gsg->add_url(
							trailingslashit( $home ),
							( '' !== $front_gmt ? $gsg->get_timestamp_from_my_sql( $front_gmt ) : 0 ),
							$gsg->get_option( 'cf_home' ),
							$gsg->get_option( 'pr_home' )
						);
					}
				} else {
					$gsg->add_url(
						trailingslashit( $home ),
						$lm,
						$gsg->get_option( 'cf_home' ),
						$gsg->get_option( 'pr_home' )
					);
				}
			}
		}

		if ( $gsg->is_xsl_enabled() && true === $gsg->get_option( 'b_html' ) ) {
			if(is_multisite()) {
				if(isset(get_blog_option( get_current_blog_id(), 'sm_options' )['sm_b_sitemap_name'])) {
					$sm_sitemap_name = get_blog_option( get_current_blog_id(), 'sm_options' )['sm_b_sitemap_name'];
				}
			} else if(isset(get_option('sm_options')['sm_b_sitemap_name'])) $sm_sitemap_name = get_option('sm_options')['sm_b_sitemap_name'];
			if(!isset($sm_sitemap_name)) $sm_sitemap_name = 'sitemap';
			$gsg->add_url(
				str_replace('.html', $sm_sitemap_name . '.html', $gsg->get_xml_url( 'main', '', array( 'html' => true ) ) ),
				$lm
			);
		}

		do_action( 'sm_buildmap' );
	}

	/**
	 * Generates the author sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg instence of sitemap generator class.
	 */
	public function build_authors( $gsg ) {
		/**
		 * Use the wpdb global variable
		 *
		 * @var $wpdb wpdb
		 * */
		global $wpdb;

		// Unfortunately there is no API function to get all authors, so we have to do it the dirty way...
		// We retrieve only users with published and not password protected enabled post types.

		$enabled_post_types = null;
		$enabled_post_types = $gsg->get_active_post_types();

		// Ensure we count at least the posts...
		$enabled_post_types_count = count( $enabled_post_types );
		if ( 0 === $enabled_post_types_count ) {
			$enabled_post_types[] = 'post';
		}
		$sql     = "SELECT DISTINCT
						u.ID,
						u.user_nicename,
						MAX(p.post_modified_gmt) AS last_post
					FROM
						{$wpdb->users} u,
						{$wpdb->posts} p
					WHERE
						p.post_author = u.ID
						AND p.post_status = 'publish'
						AND p.post_type IN(" . implode( ', ', array_fill( 0, count( $enabled_post_types ), '%s' ) ) . ")
						AND p.post_password = ''
					GROUP BY
						u.ID,
						u.user_nicename";
		$query   = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $enabled_post_types ) );
		$authors = $wpdb->get_results( $query ); // phpcs:ignore

		if ( $authors && is_array( $authors ) ) {
			$authors = $this->exclude_authors( $authors );
			
			if ( ! empty( $authors ) ) {
				foreach ( $authors as $author ) {
					$url = get_author_posts_url( $author->ID, $author->user_nicename );
					$gsg->add_url(
						$url,
						$gsg->get_timestamp_from_my_sql( $author->last_post ),
						$gsg->get_option( 'cf_auth' ),
						$gsg->get_option( 'pr_auth' )
					);
				}
			}	
		}
	}

	/**
	 * Wrap legacy filter to deduplicate calls.
	 *
	 * @param array $users Array of user objects to filter.
	 *
	 * @return array
	 */
	protected function exclude_authors( $authors ) {

		/**
		 * Filter the authors, included in XML sitemap.
		 *
		 * @param array $authors Array of user objects to filter.
		 */
		return apply_filters( 'sm_sitemap_exclude_author', $authors );
	}

	/**
	 * Filters the terms query to only include published posts
	 *
	 * @param string[] $selects Array of string.
	 * @return string[]
	 */
	public function filter_terms_query( $selects ) {
		/*
		 * The value this adds is not read anywhere. Its only live consumer,
		 * the product_cat branch of build_taxonomies(), gets its terms from
		 * this class's own raw get_terms() — plain SQL that the
		 * get_terms_fields filter never touches — so on every product-tag
		 * and product-category sitemap the database computes a correlated
		 * MAX() per term and the result is discarded. Removing the filter
		 * changes the SQL of every get_terms() call it wraps, which is more
		 * than a release-triage patch should do; tracked in #913.
		 */
		/**
		 * Global variable in functional scope for database
		 *
		 * @var wpdb $wpdb  Global variable for wpdb
		 */
		global $wpdb;
		$selects[] = "
		( /* ADDED BY XML SITEMAPS */
			SELECT
				/* TIMESTAMPDIFF from the epoch, not UNIX_TIMESTAMP(): the latter
				   reads its argument as being in MySQL's session time zone, which
				   WordPress never sets, so on a server whose zone is not UTC it
				   shifts an already-UTC post_date_gmt by that offset. Subtracting
				   two plain datetimes involves no zone at all. */
				TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', MAX(p.post_date_gmt)) as _mod_date
			FROM
				{$wpdb->posts} p,
				{$wpdb->term_relationships} r
			WHERE
				p.ID = r.object_id
				AND p.post_status = 'publish'
				AND p.post_password = ''
				AND r.term_taxonomy_id = tt.term_taxonomy_id
		) as _mod_date
		 /* END ADDED BY XML SITEMAPS */
		";

		return $selects;
	}

	/**
	 * Generates the taxonomies sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg Instance of sitemap generator.
	 * @param string                 $taxonomy The Taxonomy.
	 */
	public function build_taxonomies( $gsg, $taxonomy ) {

		$offset         = $taxonomy;
		$links_per_page = $gsg->get_entries_per_page();
		if ( gettype( $links_per_page ) !== 'integer' ) {
			$links_per_page = (int)1000;
		}
		if ( strpos( $taxonomy, '-' ) !== false ) {
			$offset   = substr( $taxonomy, strrpos( $taxonomy, '-' ) + 1 );
			$taxonomy = str_replace( '-' . $offset, '', $taxonomy );
		} else {
			$offset = 1;
		}
		$temp_offset = $offset;
		$offset = intval( $offset );
		if ( 0 === $offset ) {
			$taxonomy = $taxonomy . '-' . $temp_offset;
			$links_per_page = $this->linkPerPage;
		} else {
			$offset = ( --$offset ) * $links_per_page;
		}
		$enabled_taxonomies = $this->get_enabled_taxonomies( $gsg );
		if ( in_array( $taxonomy, $enabled_taxonomies, true ) ) {

			$excludes = array();

			$excl_cats = $gsg->get_option( 'b_exclude_cats' ); // Excluded cats.
			if ( $excl_cats ) {
				$excludes = $excl_cats;
			}
			add_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
			/*
			$terms = get_terms(
				$taxonomy,
				array(
					'number'       => $links_per_page,
					'offset'       => $offset,
					'hide_empty'   => true,
					'hierarchical' => false,
					'exclude'      => $excludes,
				)
			);
			*/
			$queryArr = [
				'taxonomy'		=> $taxonomy,
				'number'		=> $links_per_page,
				'offset'		=> $offset,
				'exclude'		=> $excludes,
			];
			$queryArr['hide_empty'] = apply_filters( 'sm_sitemap_taxonomy_hide_empty', true );
			if (preg_match('/(post_tag|category)/', $taxonomy)) {
				$queryArr['hierarchical'] = false;
			}
			$terms = array_values(
				array_unique(
					array_filter(
						$this->get_terms($queryArr),
						function ($term) use ($taxonomy) {
							return $term->taxonomy === $taxonomy;
						}
					),
					SORT_REGULAR
				)
			);
			
			remove_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
	
			//$terms = array_values(array_unique($terms, SORT_REGULAR));

			/**
			 * Filter: 'sm_exclude_from_sitemap_by_term_ids' - Allow excluding terms by ID.
			 *
			 * @param array $terms_to_exclude The terms to exclude.
			 */
			$terms_to_exclude = apply_filters( 'sm_exclude_from_sitemap_by_term_ids', [] );

			$step          = 1;
			$size_of_terms = count( $terms );

			// One grouped query for the whole page, instead of one per term
			// inside the loop below.
			$term_ids_on_page = array();
			foreach ( $terms as $term_to_prime ) {
				if ( isset( $term_to_prime->term_id ) ) {
					$term_ids_on_page[] = $term_to_prime->term_id;
				}
			}
			$this->primeTaxonomyUpdatedDates( $term_ids_on_page );

			for ( $tax_count = 0; $tax_count < $size_of_terms; $tax_count++ ) {
				$term = $terms[ $tax_count ];

				if ( in_array( $term->term_id, $terms_to_exclude ) ) {
					$step++;
					continue;
				}

				switch ( $term->taxonomy ) {
					case 'category':
						$gsg->add_url( get_term_link( $term, $step ), $this->getTaxonomyUpdatedDate($term->term_id) ?: 0, $gsg->get_option( 'cf_cats' ), $gsg->get_option( 'pr_cats' ) );
						break;
					case 'product_cat':
						// _mod_date comes from filter_terms_query()'s aggregate,
						// which only exists when the terms were fetched through
						// WP_Term_Query. This method's terms come from the
						// class's own raw get_terms(), which selects from terms
						// and term_taxonomy alone — so the property is simply
						// absent here, raising a PHP 8 warning (itself enough to
						// corrupt an XML response) and passing null as the
						// lastmod. Fall back to the same per-term lookup the
						// other branches use.
						$product_cat_mod = isset( $term->_mod_date )
							? $term->_mod_date
							: ( $this->getTaxonomyUpdatedDate( $term->term_id ) ?: 0 );
						$gsg->add_url( get_term_link( $term, $step ), $product_cat_mod, $gsg->get_option( 'cf_product_cat' ), $gsg->get_option( 'pr_product_cat' ) );
						break;
					case 'post_tag':
						$gsg->add_url( get_term_link( $term, $step ), $this->getTaxonomyUpdatedDate($term->term_id) ?: 0, $gsg->get_option( 'cf_tags' ), $gsg->get_option( 'pr_tags' ) );
						break;
					default:
						$gsg->add_url( get_term_link( $term, $step ), $this->getTaxonomyUpdatedDate($term->term_id) ?: 0, $gsg->get_option( 'cf_' . $term->taxonomy ), $gsg->get_option( 'pr_' . $term->taxonomy ) );
						break;
				}
				$step++;
			}
		}
	}

	/*
		get last updated date of taxonomy post 
		returns timestamp (int)
	*/
	/**
	 * Per-request memo for getTaxonomyUpdatedDate().
	 *
	 * Keyed by term id. A null value means "looked up, no usable date",
	 * which is distinct from a key being absent.
	 *
	 * @since 4.1.25
	 * @var array
	 */
	private static $taxonomyUpdatedDateCache = array();

	/**
	 * Resolve the newest post date for many terms in one query.
	 *
	 * getTaxonomyUpdatedDate() answers for a single term, and a taxonomy
	 * sitemap asks about every term on the page — a thousand by default. Run
	 * per term that is a thousand three-table joins on one request, which is
	 * a real timeout risk on a large catalogue and was the reason the
	 * product_cat branch originally read a precomputed column instead.
	 *
	 * This asks the same question once, grouped, fills the memo, resolves
	 * the legacy no-GMT case in one more grouped query, and finally records
	 * a null for any term that has no usable date at all — so the per-term
	 * path never re-asks for a term this has already considered.
	 *
	 * @since 4.1.25
	 * @param array $term_ids Term ids about to be rendered.
	 * @return void
	 */
	private function primeTaxonomyUpdatedDates( $term_ids ) {
		global $wpdb;

		$wanted = array();
		foreach ( (array) $term_ids as $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id > 0 && ! array_key_exists( $term_id, self::$taxonomyUpdatedDateCache ) ) {
				$wanted[ $term_id ] = true;
			}
		}
		if ( empty( $wanted ) ) {
			return;
		}

		$wanted = array_keys( $wanted );

		/*
		 * Chunked, for two reasons.
		 *
		 * A page can carry up to sm_links_page terms, and that ceiling is
		 * 50,000 — one bound parameter each would build a statement large
		 * enough for the server to reject outright.
		 *
		 * And failure is recorded per chunk rather than per page. A rejected
		 * or timed-out statement is indistinguishable from "no rows" here,
		 * so the ids it covered have to be treated as answered to stop the
		 * per-term path re-asking about each of them; without chunking, one
		 * failure would do that to every term on the page and cost the whole
		 * taxonomy sitemap its <lastmod>. Confined to a chunk, the rest of
		 * the page still resolves normally.
		 */
		foreach ( array_chunk( $wanted, 500 ) as $chunk ) {
			$chunk_failed = false;
			$placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated, values are bound below.
					"SELECT tt.term_id AS term_id, MAX(p.post_date_gmt) AS newest
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id IN ($placeholders)
						AND p.post_status = 'publish'
						AND p.post_date_gmt > '0000-00-00 00:00:00'
					GROUP BY tt.term_id",
					$chunk
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$chunk_failed = true;
			}

			foreach ( (array) $rows as $row ) {
				if ( empty( $row->newest ) ) {
					continue;
				}
				$timestamp = strtotime( $row->newest . ' +0000' );
				if ( $timestamp > 0 ) {
					self::$taxonomyUpdatedDateCache[ (int) $row->term_id ] = $timestamp;
				}
			}

			// Whatever the first query did not answer is resolved here, in
			// one more grouped query for the same chunk. Skipped once that
			// chunk has already failed: the second query is aimed at the
			// same database and would only add load.
			$unresolved = array();
			foreach ( $chunk as $term_id ) {
				if ( ! array_key_exists( $term_id, self::$taxonomyUpdatedDateCache ) ) {
					$unresolved[] = $term_id;
				}
			}

			if ( ! $chunk_failed && ! empty( $unresolved ) && function_exists( 'get_gmt_from_date' ) ) {
				$legacy_placeholders = implode( ', ', array_fill( 0, count( $unresolved ), '%d' ) );
				$legacy_rows         = $wpdb->get_results(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated, values are bound below.
						"SELECT tt.term_id AS term_id, MAX(p.post_date) AS newest
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						WHERE tt.term_id IN ($legacy_placeholders)
							AND p.post_status = 'publish'
						GROUP BY tt.term_id",
						$unresolved
					)
				);

				foreach ( (array) $legacy_rows as $row ) {
					if ( empty( $row->newest ) || '0000-00-00 00:00:00' === $row->newest ) {
						continue;
					}
					$timestamp = strtotime( get_gmt_from_date( $row->newest ) . ' +0000' );
					if ( $timestamp > 0 ) {
						self::$taxonomyUpdatedDateCache[ (int) $row->term_id ] = $timestamp;
					}
				}
			}
		}

		/*
		 * Anything still unanswered has no usable date — or belonged to a
		 * chunk whose query failed, which is indistinguishable from that
		 * here. Either way it is recorded.
		 *
		 * The alternative, leaving failed ids absent so the per-term path
		 * retries them, was tried and rejected: within this request that is
		 * one or two three-table joins for every such term, up to 500 per
		 * failed chunk, aimed at a database that has just told us it cannot
		 * serve queries. The governing rule when the database is failing is
		 * bounded work and degraded output, not unbounded retries — a
		 * sitemap missing some <lastmod> hints is a far better outcome than
		 * one that finishes the job of taking the database down.
		 *
		 * The cost is contained on both axes: chunking limits a failure to
		 * the terms it actually touched, and this memo lives for one
		 * request, so the next request starts empty and tries again.
		 */
		foreach ( $wanted as $term_id ) {
			if ( ! array_key_exists( $term_id, self::$taxonomyUpdatedDateCache ) ) {
				self::$taxonomyUpdatedDateCache[ $term_id ] = null;
			}
		}
	}

	private function getTaxonomyUpdatedDate($term_id){
		global $wpdb;

		/*
		 * Memoised for the request, in a property rather than a local
		 * static so primeTaxonomyUpdatedDates() can fill it in bulk. This
		 * is called once per term while a taxonomy sitemap is built — up to
		 * sm_links_page terms, 1000 by default — and each call is a
		 * three-table join with a filesort, so on a large catalogue the
		 * prime is the difference between one query and a thousand.
		 */
		$cache   = &self::$taxonomyUpdatedDateCache;
		$term_id = (int) $term_id;
		if ( array_key_exists( $term_id, $cache ) ) {
			return $cache[ $term_id ];
		}

		/*
		 * Two corrections here, both of which produced a <lastmod> in the
		 * future — the symptom this release was reported for.
		 *
		 * post_date_gmt, not post_date. WordPress pins PHP's default
		 * timezone to UTC, so strtotime() on the site-local post_date
		 * column reads the wall-clock time as though it were UTC. On a
		 * UTC+11 site that puts every fresh term 11 hours ahead, and the
		 * newest categories — the ones that matter most — were the ones
		 * that looked furthest into the future.
		 *
		 * post_status = 'publish'. The query had no status filter, so a
		 * scheduled post (post_status 'future') or a draft could be picked
		 * as the term's newest, dating the term to a post that is not in
		 * the sitemap and has not been published yet.
		 *
		 * A zeroed post_date_gmt — left behind by older WordPress versions
		 * and by direct SQL imports — cannot be ordered correctly in SQL at
		 * all, because substituting the local column would compare one row's
		 * UTC value against another row's local value; on a UTC+11 site a
		 * zeroed row could then outrank a genuinely newer one by the whole
		 * offset.
		 *
		 * So the two cases are asked separately, and each query returns the
		 * single row it needs. The common one orders by post_date_gmt among
		 * rows that actually have it, which is a straight UTC comparison.
		 * Only when a term has no such row at all does the legacy query run,
		 * ordering by the local column and converting the result. This is
		 * called once per term, so hydrating a batch of candidates per call
		 * — 50 rows across a thousand-term taxonomy is fifty thousand — to
		 * cover a case that is rare and, in a mixed database, not fully
		 * solvable in SQL anyway, is not a trade worth making.
		 *
		 * In a database mixing zeroed and populated rows the newest
		 * populated row wins. That can be slightly older than the true
		 * newest, which is the safe direction to be wrong in: a lastmod
		 * that lags is a weaker hint, not a false claim.
		 */
		$newest_gmt = $wpdb->get_var($wpdb->prepare("
			SELECT p.post_date_gmt
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d AND p.post_status = 'publish'
				AND p.post_date_gmt > '0000-00-00 00:00:00'
			ORDER BY p.post_date_gmt DESC
			LIMIT 1
		", $term_id));

		if (!empty($newest_gmt)) {
			$timestamp       = strtotime($newest_gmt . ' +0000');
			$cache[$term_id] = $timestamp > 0 ? $timestamp : null;
			return $cache[$term_id];
		}

		// Legacy fallback: this term has no row carrying a GMT date.
		if (!function_exists('get_gmt_from_date')) {
			$cache[$term_id] = null;
			return $cache[$term_id];
		}

		$newest_local = $wpdb->get_var($wpdb->prepare("
			SELECT p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d AND p.post_status = 'publish'
			ORDER BY p.post_date DESC
			LIMIT 1
		", $term_id));

		if (empty($newest_local) || '0000-00-00 00:00:00' === $newest_local) {
			$cache[$term_id] = null;
			return $cache[$term_id];
		}

		$timestamp       = strtotime(get_gmt_from_date($newest_local) . ' +0000');
		$cache[$term_id] = $timestamp > 0 ? $timestamp : null;

		return $cache[$term_id];
	}

	/**
	 * Returns the enabled taxonomies. Only taxonomies with posts are returned.
	 *
	 * @param GoogleSitemapGenerator $gsg Google sitemap generator's instance.
	 * @return array
	 */
	public function get_enabled_taxonomies( GoogleSitemapGenerator $gsg ) {
		$enabled_taxonomies = $gsg->get_option( 'in_tax' );
		if ( $gsg->get_option( 'in_tags' ) ) {
			$enabled_taxonomies[] = 'post_tag';
		}
		if ( $gsg->get_option( 'in_cats' ) ) {
			$enabled_taxonomies[] = 'category';
		}
		return $enabled_taxonomies;
	}

	/**
	 * Returns the enabled Product tags. Only Product Tags with posts are returned.
	 *
	 * @param GoogleSitemapGenerator $gsg Instance of sitemap generator.
	 * @param int                    $offset Offset.
	 * @return void
	 */
	public function build_product_tags( GoogleSitemapGenerator $gsg, $offset ) {
		$links_per_page = $gsg->get_entries_per_page();
		if ( gettype( $links_per_page ) !== 'integer' ) {
			$links_per_page = (int) 1000;
		}
		$offset = (intval(--$offset)) * $links_per_page;

		add_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
		$terms = get_terms(
			'product_tag',
			array(
				'number' => $links_per_page,
				'offset' => $offset,
			)
		);
		remove_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
		$term_array = array();

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_array[] = $term->name;
				$url          = get_term_link( $term );
				//$gsg->add_url( $url, $term->_mod_date, $gsg->get_option( 'cf_tags' ), $gsg->get_option( 'pr_tags' ), $term->ID, array(), array(), '' );
				$gsg->add_url( $url, $this->getProductUpdatedDate($term->term_id, 'product_tag'), $gsg->get_option( 'cf_tags' ), $gsg->get_option( 'pr_tags' ), $term->ID, array(), array(), '' );
			}
		}
	}

	/**
	 * Returns the enabled Product Categories. Only Product Categories with posts are returned.
	 *
	 * @param GoogleSitemapGenerator $gsg Instance of sitemap generator.
	 * @param int                    $offset Offset.
	 * @return void
	 */
	public function build_product_categories( GoogleSitemapGenerator $gsg, $offset ) {
		$links_per_page = $gsg->get_entries_per_page();
		if ( gettype( $links_per_page ) !== 'integer' ) {
			//$links_per_page = (int) 1000;
			$links_per_page = (int)$links_per_page;
		}
		$offset = (intval(--$offset)) * $links_per_page;
		$excludes       = array();
		$excl_cats      = $gsg->get_option( 'b_exclude_cats' ); // Excluded cats.
		if ( $excl_cats ) {
			$excludes = $excl_cats;
		}
		add_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
		$category = get_terms(
			'product_cat',
			array(
				'number'  => $links_per_page,
				'offset'  => $offset,
				'exclude' => $excludes,
			)
		);
		remove_filter( 'get_terms_fields', array( $this, 'filter_terms_query' ), 20, 2 );
		$cat_array = array();
		if ( ! empty( $category ) && ! is_wp_error( $category ) ) {
			$step = 1;
			foreach ( $category as $cat ) {
				$cat_array[] = $cat->name;
				if ( $cat && wp_count_terms( $cat->name, array( 'hide_empty' => true ) ) > 0 ) {
					$step++;
					$url = get_term_link( $cat );
					$gsg->add_url( $url, $this->getProductUpdatedDate($cat->term_id, 'product_cat'), $gsg->get_option( 'cf_product_cat' ), $gsg->get_option( 'pr_product_cat' ), $cat->ID, array(), array(), '' );
				}
			}
		}
	}

	/* Get last product updated date by tag ID */
	private function getProductUpdatedDate($term_id, $taxonomy){
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => 1,
			'tax_query' => array(
				array(
					'taxonomy' => $taxonomy,
					'field' => 'id',
					'terms' => $term_id,
				),
			),
			'orderby' => 'modified',
			'order' => 'DESC',
		);
	
		$products = new WC_Product_Query($args);
		$product_results = $products->get_products();

		if ($product_results) {
			$product = array_shift($product_results);
			// getTimestamp() is a true UTC epoch. Formatting the WC_DateTime
			// to 'Y-m-d H:i:s' first renders it in the store's local zone
			// and strtotime() then reads it back as UTC, shifting every
			// product term by the site's offset.
			$date_modified = $product->get_date_modified();
			if (!$date_modified) {
				return false;
			}
			return $date_modified->getTimestamp();
		}
		return false;
	}

	/**
	 * Generates the external sitemap
	 *
	 * @param GoogleSitemapGenerator $gsg Instance of sitemap generator.
	 */
	public function build_externals( $gsg ) {
		$pages = $gsg->get_pages();
		if ( $pages && is_array( $pages ) && count( $pages ) > 0 ) {
			foreach ( $pages as $page ) {
				// Disabled phpcs for backward compatibility .
				// phpcs:disable
				$url         = ! empty( $page->get_url() ) ? $page->get_url() : $page->url;
				$change_freq = ! empty( $page->get_change_freq() ) ? $page->get_change_freq() : $page->change_freq;
				$priority    = ! empty( $page->get_priority() ) ? $page->get_priority() : $page->priority;
				$last_mod    = ! empty( $page->get_last_mod() ) ? $page->get_last_mod() : $page->last_mod;
				// phpcs:enable
				/**
				 * Description for $page variable.
				 *
				 * @var $page GoogleSitemapGeneratorPage
				 */
				$gsg->add_url( $url, self::normalise_external_last_mod( $last_mod ), $change_freq, $priority );
			}
		}
	}

	/**
	 * Keep an admin-entered external-page date out of the clamp's teeth.
	 *
	 * These dates are not read from the database: an administrator typed a
	 * DATE into the settings screen, and it is stored at UTC midnight of
	 * that day. On a site ahead of UTC, midnight of "today" is still in the
	 * future for the rest of the local morning, so gsg_clamp_lastmod() would
	 * refuse it and the entry would lose its <lastmod> entirely — for a
	 * completely routine admin action, and where 4.1.24 did emit something.
	 *
	 * The test is on the DATE, not on how many hours ahead the timestamp is.
	 * A window measured from the current instant gives different answers for
	 * the same stored value as the day goes on — a page dated tomorrow would
	 * fall outside a fourteen-hour window at 09:00 UTC and inside it at
	 * 11:00 — so the rescue would come and go by the hour. Comparing the
	 * entered date against today's date in the site's own timezone answers
	 * the question actually being asked: did the administrator mean "today"?
	 *
	 * If they did, it is reported as the start of the current UTC day: never
	 * in the future, and stable, moving once a day rather than on every
	 * request. Note this will read as the PREVIOUS date to an administrator
	 * on a UTC-ahead site for as long as UTC has not reached their date
	 * yet. That is not worth engineering around — <lastmod> is emitted in
	 * UTC, so any instant chosen inside that window renders on the same UTC
	 * date, and a date that lags by hours is a weaker hint rather than a
	 * false claim. Advertising the future date instead is the thing that
	 * costs something.
	 *
	 * Once UTC passes the entered midnight the stored value is used
	 * unchanged.
	 *
	 * Any other future date is a deliberate choice and is left for the clamp
	 * to drop. The storage convention itself is tracked in #912.
	 *
	 * @since 4.1.25
	 * @param mixed $last_mod Stored timestamp, 0 when unset.
	 * @return int Timestamp safe to render, or 0 when unknown.
	 */
	private static function normalise_external_last_mod( $last_mod ) {
		$last_mod = (int) $last_mod;
		if ( $last_mod <= 0 ) {
			return 0;
		}

		$now = time();
		if ( $last_mod <= $now ) {
			return $last_mod;
		}

		// Stored at UTC midnight of the day the administrator typed, so the
		// date is read back the same way. current_time() gives today's date
		// in the site's timezone, which is the calendar they were looking at.
		$entered_date = gmdate( 'Y-m-d', $last_mod );
		$local_today  = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d', $now );

		if ( $entered_date === $local_today ) {
			return (int) strtotime( gmdate( 'Y-m-d', $now ) . ' 00:00:00 +0000' );
		}

		return $last_mod;
	}

	/**
	 * The site-wide "anything changed" timestamp, with poisoned rows handled.
	 *
	 * Shared because two callers need the same answer: index() gives it to
	 * nearly every <sitemap> entry, and build_misc() gives it to the home
	 * page and the HTML sitemap. While only index() applied the recovery, a
	 * site with one bogus future row got rescued dates in the index while
	 * the home page silently lost its <lastmod> — one value producing two
	 * different outcomes inside a single build.
	 *
	 * @since 4.1.25
	 * @return int UTC timestamp, or 0 when the site has no usable date.
	 */
	private static function resolve_blog_lastmod() {
		global $wpdb;

		/*
		 * Memoised for the request. Both callers can run in one build, and
		 * on a poisoned site the work behind this is a full unindexed scan
		 * plus two option writes — worth doing once, not once per caller.
		 * A null means "not resolved yet", which is distinct from the 0 this
		 * legitimately returns for a site with no usable date.
		 */
		static $resolved = null;
		if ( null !== $resolved ) {
			return $resolved;
		}

		/*
		 * Guarded because get_lastpostmodified() returns false on a site
		 * with no published posts, and `false . ' +0000'` is the string
		 * ' +0000', which strtotime() resolves to the CURRENT TIME rather
		 * than failing. That would hand every entry in the index a lastmod
		 * that moves on every request — the churn gsg_clamp_lastmod() is
		 * written to avoid, and one it cannot detect, because "now" is
		 * always a plausible date.
		 */
		$last_post_modified = get_lastpostmodified( 'gmt' );
		$blog_update        = ( ! empty( $last_post_modified ) && '0000-00-00 00:00:00' !== $last_post_modified )
			? strtotime( $last_post_modified . ' +0000' )
			: 0;

		/*
		 * This one value is handed to nearly every entry in the index —
		 * misc, each taxonomy, product tags, product categories, externals,
		 * authors and archives all receive it. That makes it a single point
		 * of failure for the whole file: gsg_clamp_lastmod() drops a date it
		 * cannot trust, so if this value is untrustworthy EVERY <sitemap>
		 * element loses its <lastmod> at once rather than just the offending
		 * one.
		 *
		 * get_lastpostmodified() takes the maximum across published posts,
		 * so a single row with a bogus future post_modified_gmt — importers
		 * write these, as does wp_insert_post() with an explicit future date
		 * and status 'publish' — is enough to poison it.
		 *
		 * So when the value is implausibly far ahead, ask for the newest row
		 * that is NOT in the future instead of surrendering the whole index.
		 * Healthy sites never reach this branch and pay no extra query.
		 * Per-entry timestamps would confine the loss properly; see #910.
		 */
		if ( $blog_update && $blog_update > ( time() + GSG_LASTMOD_SKEW_TOLERANCE ) ) {
			/*
			 * Cached, because nothing here repairs the row that triggered
			 * it: get_lastpostmodified() keeps returning the poisoned value,
			 * so without a cache every sitemap-index request on an affected
			 * site would repeat the scan below, permanently, and only for
			 * the sites already in trouble. An hour is short enough that a
			 * corrected row shows up promptly and long enough that crawler
			 * traffic cannot turn this into a load problem.
			 */
			$newest_settled = get_transient( 'sm_lastmod_settled' );

			if ( false === $newest_settled ) {
				/*
				 * Restricted to public post types, as _get_last_post_time()
				 * is. nav_menu_item, wp_block, wp_navigation and
				 * wp_template_part rows all carry post_status 'publish', so
				 * an unrestricted MAX() would answer "when the menu or a
				 * template was last saved" and hand that to every entry.
				 */
				$public_types = get_post_types( array( 'public' => true ) );
				if ( empty( $public_types ) ) {
					$public_types = array( 'post', 'page' );
				}
				$type_placeholders = implode( ', ', array_fill( 0, count( $public_types ), '%s' ) );

				/*
				 * Claim the cache slot BEFORE the scan, not after. The column
				 * is unindexed, so this is a full pass over every published
				 * row; without the early write, an expiry under crawler
				 * traffic sends several concurrent requests into their own
				 * scan at once. Requests arriving during the scan read this
				 * placeholder, skip the recovery, and omit the lastmod for
				 * that one response.
				 *
				 * Minutes, not the hour the real answer gets — and not
				 * seconds either. Both ends have a failure mode.
				 *
				 * Too long: the scan is exactly what might kill the request
				 * (max_execution_time, a database timeout, memory) on
				 * precisely the large sites that reach this branch, and an
				 * hour-long placeholder would survive the death that wrote
				 * it, suppressing the recovery for an hour while the whole
				 * index plus the home page lose <lastmod>.
				 *
				 * Too short: the placeholder has to outlive the scan, and a
				 * full pass over an unindexed column on a large table is
				 * not a sub-minute operation. Expiring mid-scan lets every
				 * subsequent request start its own — the stampede this
				 * exists to prevent, on the sites it was written for.
				 *
				 * Five minutes clears any scan that was going to finish
				 * within PHP's usual execution limit, and bounds the
				 * suppression after a fatal to the same five minutes.
				 */
				set_transient( 'sm_lastmod_settled', '', 5 * MINUTE_IN_SECONDS );

				$newest_settled = $wpdb->get_var(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated, values are bound below.
						"SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($type_placeholders) AND post_modified_gmt <= %s",
						array_merge( array_values( $public_types ), array( gmdate( 'Y-m-d H:i:s' ) ) )
					)
				);
				/*
				 * Overwrite the placeholder with the real answer. A miss is
				 * still stored as '', so a site with nothing settled does
				 * not re-run the scan on every request either — but only
				 * when the query actually ran. get_var() returns null for a
				 * killed or timed-out query exactly as it does for "no
				 * rows", and this scan is the one most likely to be killed:
				 * an unindexed full pass over a large table. Caching a
				 * failure as a miss would leave the poisoned value in use
				 * for the next hour, stripping <lastmod> from every index
				 * entry plus the home page — precisely what the recovery
				 * exists to prevent.
				 *
				 * On failure the placeholder is shortened rather than
				 * deleted. Deleting it removes the only backoff, and these
				 * failures — max_statement_time, a lost connection, a lock
				 * timeout on an unindexed full-table MAX() — are
				 * deterministic on the very sites that reach this branch, so
				 * every subsequent and concurrent request would re-run the
				 * same doomed scan. A short negative window retries promptly
				 * without turning a database problem into a load problem.
				 */
				if ( ! empty( $wpdb->last_error ) ) {
					set_transient( 'sm_lastmod_settled', '', MINUTE_IN_SECONDS );
					$newest_settled = false;
				} else {
					set_transient( 'sm_lastmod_settled', ( null === $newest_settled ? '' : $newest_settled ), HOUR_IN_SECONDS );
				}
			}

			if ( ! empty( $newest_settled ) && '0000-00-00 00:00:00' !== $newest_settled ) {
				$blog_update = strtotime( $newest_settled . ' +0000' );
			}
		}

		$resolved = (int) $blog_update;

		return $resolved;
	}

	/**
	 * Generates the sitemap index
	 *
	 * @param GoogleSitemapGenerator $gsg Instance of sitemap generator.
	 */
	public function index( $gsg ) {
		/**
		 * Global variable for database.
		 *
		 * @var $wpdb wpdb
		 */
		global $wpdb;
		$blog_update = self::resolve_blog_lastmod();

		$links_per_page = $gsg->get_entries_per_page();
		if ( 0 === $links_per_page || is_nan( $links_per_page ) ) {
			$links_per_page = $this->linkPerPage;
			$gsg->set_option( 'links_page', $this->linkPerPage );
		}
		else if ($links_per_page > $this->maxLinksPerPage) $links_per_page = $this->maxLinksPerPage;
		$gsg->add_sitemap( 'misc', null, $blog_update );

		/**
		 * Filter: 'sm_sitemap_exclude_taxonomy' - Allow extending and modifying the taxonomies to exclude.
		 *
		 * @param array $taxonomies_to_exclude The taxonomies to exclude.
		 */
		$taxonomies_to_exclude = [];
		$default_taxonomies_to_exclude = [ 'product_tag', 'product_cat' ];
		$taxonomies_to_exclude = apply_filters( 'sm_sitemap_exclude_taxonomy', $taxonomies_to_exclude );
		if ( ! is_array( $taxonomies_to_exclude ) || empty( $taxonomies_to_exclude ) ) {
			$taxonomies_to_exclude = $default_taxonomies_to_exclude;
		} else {
			$taxonomies_to_exclude = array_merge( $taxonomies_to_exclude, $default_taxonomies_to_exclude );
		}
		$enabled_taxonomies = $this->get_enabled_taxonomies( $gsg );	
		$excl_cats = $gsg->get_option( 'b_exclude_cats' );
		$excludes = $excl_cats ? $excl_cats : array();	
		$terms_by_taxonomy = array();
		
		foreach ( $enabled_taxonomies as $taxonomy ) {
			if ( ! in_array( $taxonomy, $taxonomies_to_exclude, true ) ) {
				$terms_args = [
					'taxonomy' => $taxonomy,
					'exclude' => $excludes
				];
				$terms_args['hide_empty'] = apply_filters( 'sm_sitemap_taxonomy_hide_empty', true );
				$terms = $this->get_terms( $terms_args );
				$terms_by_taxonomy[ $taxonomy ] = $terms;
			}
		}
		
		foreach ( $terms_by_taxonomy as $taxonomy => $terms ) {
			$step = 1;
			$i = 0;
			foreach ( $terms as $term ) {
				if ( 0 === ( $i % $links_per_page ) && '' !== $term->taxonomy && taxonomy_exists( $term->taxonomy ) ) {
					$gsg->add_sitemap( $term->taxonomy,'-sitemap' . ($step === 1? '' : $step), $blog_update );
					$step++;
				}
				$i++;
			}
		}

		// If Product Tags is enabled from sitemap settings.
		if ( true === $gsg->get_option( 'product_tags' ) ) {
			$product_tags = get_terms( 'product_tag' );
			if ( ! empty( $product_tags ) && ! is_wp_error( $product_tags ) ) {
				$step                 = 1;
				$product_tags_size_of = count( $product_tags );

				for ( $product_count = 0; $product_count < $product_tags_size_of; $product_count++ ) {
					if ( 0 === ( $product_count % $links_per_page ) ) {
						//$gsg->add_sitemap( 'producttags', $step, $blog_update );
						$gsg->add_sitemap( 'producttags', '-sitemap' . ($step === 1? '' : $step), $blog_update );
						$step = ++$step;
					}
				}
			}
		}

		// If Product category is enabled from sitemap settings.
		if ( true === $gsg->get_option( 'in_product_cat' ) ) {
			$excludes  = array();
			$excl_cats = $gsg->get_option( 'b_exclude_cats' ); // Excluded cats.

			if ( $excl_cats ) {
				$excludes = $excl_cats;
			}

			$product_cat = get_terms( 'product_cat', array( 'exclude' => $excludes ) );

			if ( ! empty( $product_cat ) && ! is_wp_error( $product_cat ) ) {
				$step              = 1;
				$product_cat_count = count( $product_cat );
				for ( $product_count = 0; $product_count < $product_cat_count; $product_count++ ) {
					if ( 0 === ( $product_count % $links_per_page ) ) {
						//$gsg->add_sitemap( 'productcat', $step, $blog_update );
						$gsg->add_sitemap( 'productcat', '-sitemap' . ($step === 1? '' : $step), $blog_update );
						$step = ++$step;
					}
				}
			}
		}

		$pages = (array)$gsg->get_pages();
		if ( count( $pages ) > 0 ) {
			foreach ( $pages as $page ) {
				$url = ! empty( $page->get_url() ) ? $page->get_url() : ( property_exists( $page, '_url' ) ? $page->_url : '' );
				if ( $page instanceof GoogleSitemapGeneratorPage && $url ) {
					$gsg->add_sitemap( 'externals-sitemap', null, $blog_update );
					break;
				}
			}
		}

		$enabled_post_types = $gsg->get_active_post_types();

		//checking for products enabled
		if($gsg->get_option( 'in_product_assortment' ) !== null && $gsg->get_option( 'in_product_assortment' ) !== true){
			$enabled_post_types = array_filter($enabled_post_types, function($value) {
				return $value !== 'product';
			});
		}

		$has_enabled_post_types_posts = false;
		$has_posts                    = false;

		if ( count( $enabled_post_types ) > 0 ) {

			$excluded_post_ids = $gsg->get_excluded_post_ids( $gsg );
			$not_allowed_slugs = $gsg->robots_disallowed();
			$excluded_post_ids = array_unique( array_merge( $excluded_post_ids, $not_allowed_slugs ), SORT_REGULAR );
			$gsg->set_option( 'b_exclude', $excluded_post_ids );
			$gsg->save_options();
			$ex_post_s_q_l           = '';
			$excluded_post_ids_count = count( $excluded_post_ids );
			if ( $excluded_post_ids_count > 0 ) {
				$ex_post_s_q_l = 'AND p.ID NOT IN (' . implode( ',', $excluded_post_ids ) . ')';
			}
			$excluded_category_i_d_s       = $gsg->get_excluded_category_i_ds( $gsg );
			$ex_cat_s_q_l                  = '';
			$excluded_category_i_d_s_count = count( $excluded_category_i_d_s );
			if ( $excluded_category_i_d_s_count > 0 ) {
				$ex_cat_s_q_l = "AND ( p.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $excluded_category_i_d_s ) . ')))';
			}
			foreach ( $enabled_post_types as $post_type_custom ) {
				// phpcs:disable
				$prp = $wpdb->prepare(
					"SELECT
					COUNT(p.ID) AS `numposts`,
					MAX(p.post_modified_gmt) as `last_mod`
					FROM
						{$wpdb->posts} p
					WHERE
						p.post_password = ''
						AND p.post_type = '%s'
						AND p.post_status = 'publish'
						" . $ex_post_s_q_l . ""
						. $ex_cat_s_q_l . "
					ORDER BY
						p.post_date_gmt DESC",
						$post_type_custom
				);
				$posts = $wpdb->get_results($prp);

				if ( $posts ) {
					if ( 'post' === $post_type_custom ) {
						$has_posts = true;
					}
					$has_enabled_post_types_posts = true;

					foreach ( $posts as $post ) {
						$step = 1;
						for ( $i = 0; $i < $post->numposts; $i++ ) {
							if ( 0 === ( $i % $links_per_page ) ) {
								//$gsg->add_sitemap( 'pt', $post_type_custom . '-p' . $step . '-' . sprintf( '%04d-%02d', $post->year, $post->month ), $gsg->get_timestamp_from_my_sql( $post->last_mod ), 'p' . $step );
								$gsg->add_sitemap( 'pt', $post_type_custom . '-sitemap' . ($step === 1? '' : $step) , $gsg->get_timestamp_from_my_sql( $post->last_mod ) );
								$step = ++$step;
							}
						}
						// $gsg->add_sitemap( 'pt', $post_type_custom . '-' . sprintf( '%04d-%02d', $post->year, $post->month ), $gsg->get_timestamp_from_my_sql( $post->last_mod ) );
					}
				}
				// phpcs:enable
			}
		}

		// Only include authors if there is a public post with a enabled post type.
		if ( $gsg->get_option( 'in_auth' ) && $has_enabled_post_types_posts ) {
			$gsg->add_sitemap( 'authors-sitemap', null, $blog_update );
		}

		// Only include archived if there are posts with postType post.
		if ( $gsg->get_option( 'in_arch' ) && $has_posts ) {
			$gsg->add_sitemap( 'archives-sitemap', null, $blog_update );
		}

		/**
		 * Filter: 'sm_sitemap_index' - Allow extending and modifying the xml links to include.
		 *
		 * @param array $sitemap_custom_items The custom xml link to include.
		 */
		$sitemap_custom_items = [];
		$sitemap_custom_items = apply_filters( 'sm_sitemap_index', $sitemap_custom_items );
		if ( ! is_array( $sitemap_custom_items ) ) {
			$sitemap_custom_items = [];
		}
		if ( ! empty( $sitemap_custom_items ) ) {
			foreach ( $sitemap_custom_items as $sitemap_custom_item ) {
				$title = ( isset( $sitemap_custom_item['title'] ) ) ? $sitemap_custom_item['title'] : '';
				$modified = ( isset( $sitemap_custom_item['modified'] ) ) ? strtotime( $sitemap_custom_item['modified'] ) : '';
				if ( $title != '' && $modified != '' ) {
					$gsg->add_sitemap( $title, null, $modified );
				}
			}
		}
	}

	/**
	 * Return the URL to the sitemap related to a specific post
	 *
	 * @param array                  $urls Post sitemap urls.
	 * @param GoogleSitemapGenerator $gsg Instance of google sitemap generator.
	 * @param int                    $post_id The post ID.
	 *
	 * @return string[]
	 */
	public function get_sitemap_url_for_post( array $urls, $gsg, $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			/*
			 * The zero-date sentinel matters more here than anywhere else in
			 * this file. get_timestamp_from_my_sql('0000-00-00 00:00:00')
			 * returns a 1999 timestamp, and this one does not merely become
			 * a wrong <lastmod> — it is formatted into the sitemap's URL, so
			 * the post would be pointed at pt/<type>-1999-11.
			 *
			 * Note this only stops a zero date from producing a nonsense
			 * URL. The <type>-<Y-m> shape itself does not match any sitemap
			 * this plugin registers — index() uses <type>-sitemap[N] — so
			 * the URL is wrong whatever date reaches it. That is older than
			 * this release and is tracked separately in #911.
			 */
			$candidates = array();
			if ( ! empty( $post->post_modified_gmt ) && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
				$candidates[] = $post->post_modified_gmt;
			}
			if ( ! empty( $post->post_date_gmt ) && '0000-00-00 00:00:00' !== $post->post_date_gmt ) {
				$candidates[] = $post->post_date_gmt;
			}
			if ( empty( $candidates ) && function_exists( 'get_gmt_from_date' ) ) {
				foreach ( array( $post->post_modified, $post->post_date ) as $local ) {
					if ( ! empty( $local ) && '0000-00-00 00:00:00' !== $local ) {
						$candidates[] = get_gmt_from_date( $local );
						break;
					}
				}
			}

			if ( ! empty( $candidates ) ) {
				$last_modified = $gsg->get_timestamp_from_my_sql( $candidates[0] );
				if ( $last_modified > 0 ) {
					$urls[] = $gsg->get_xml_url( 'pt', $post->post_type . '-' . gmdate( 'Y-m', $last_modified ) );
				}
			}
		}

		return $urls;
	}

	public function get_terms( $args = [] ) {
		global $wpdb;

		$taxonomy = ( isset( $args['taxonomy'] ) && null !== $args['taxonomy'] ) ? $args['taxonomy'] : false;

		$sql = 'SELECT DISTINCT *';
		$sql .= ' FROM '.$wpdb->prefix.'terms as t';
		$sql .= ' INNER JOIN '.$wpdb->prefix.'term_taxonomy as tt';
		$sql .= ' WHERE `tt`.taxonomy = \'' . $taxonomy . '\'';
		$sql .= ' AND `tt`.term_id = `t`.term_id';

		if ( ! empty( $args ) ) {
			if ( isset( $args['hide_empty'] ) && $args['hide_empty'] === true ) {
				$sql .= ' AND `tt`.count != 0';
			}
			if ( isset( $args['hierarchical'] ) && $args['hierarchical'] === true ) {
				$sql .= ' AND `tt`.parent != 0';
			}
			if ( isset( $args['exclude'] ) && is_array( $args['exclude'] ) && ! empty( $args['exclude'] ) ) {
				foreach ( $args['exclude'] as $term_id ) {
					$sql .= ' AND `tt`.term_id != ' . $term_id;
				}
			}
			$sql .= ' ORDER BY t.name ASC';
			if ( isset( $args['number'] ) && $args['number'] != '' ) {
				$sql .= ' LIMIT ' . $args['number'];
			}
			if ( isset( $args['offset'] ) && $args['offset'] != ''  ) {
				$sql .= ' OFFSET ' . $args['offset'];
			}
		}
		
		$result = $wpdb->get_results($sql);
		
		return $result; 
	}
}

if ( defined( 'WPINC' ) ) {
	new GoogleSitemapGeneratorStandardBuilder();
}
