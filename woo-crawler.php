<?php
/*
Plugin Name: Woo Crawler
Plugin URI: https://sps.vn
Description: Scan and collect information from any url. Customize the information fields you need in a very flexible way.
Author: spsdev
Author URI: http://dev.sps.vn
Version: 1.0
Text Domain: woocrl
*/
if (!defined('ABSPATH')) exit;

define('WOOCRL_PLUGIN_FILE', __FILE__);
define('WOOCRL_URL', untrailingslashit(plugins_url( '', WOOCRL_PLUGIN_FILE)));
define('WOOCRL_PATH', dirname(WOOCRL_PLUGIN_FILE));
define('WOOCRL_BASE', plugin_basename(WOOCRL_PLUGIN_FILE));

class Woo_Crawler {

	public function __construct() {
		register_activation_hook( WOOCRL_PLUGIN_FILE, array( $this, 'activate' ) );
		/*
		Khởi tạo các biến nếu cần thiết
		 */
		
		$this->include();
		
		/*
		 Gọi các hook
		 */
		$this->hooks();
	}

	public function hooks() {
		
		add_action('wp_ajax_woocrl_scan', array($this, 'woocrl_scan'));
		add_action('wp_ajax_nopriv_woocrl_scan', array($this, 'woocrl_scan'));

		add_action('wp_ajax_woocrl_remove_crawled', array($this, 'woocrl_remove_crawled'));
		add_action('wp_ajax_nopriv_woocrl_remove_crawled', array($this, 'woocrl_remove_crawled'));

		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts') );

		add_action( 'template_include', array($this, 'crawler_page') );
		add_filter( 'init', array( $this, 'rewrites' ) );
	}

	public function include() {
		require_once WOOCRL_PATH . '/simplehtmldom/simple_html_dom.php';
		require_once WOOCRL_PATH . '/arraytocsv.php';
	}

	public function activate() {
		set_transient( 'woocrl_flush', 1, 60 );
	}

	public function enqueue_scripts() {
		wp_enqueue_style('woocrl', WOOCRL_URL . '/style.css');
		wp_enqueue_script('woocrl', WOOCRL_URL . '/script.js', array('jquery'), '', true);
		$woocrl = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('woocrl')
		);
		wp_localize_script('woocrl', 'woocrl', $woocrl);
	}

	public function crawler_page($template) {
		
		if( get_query_var( 'woocrawler', false ) !== false ) {
			$new_template = locate_template( array( 'woo-crawler-page.php' ) );
			if( '' != $new_template ) {
				$template = $new_template;
			} else {
				$new_template = WOOCRL_PATH . '/woo-crawler-page.php';
				if(file_exists($new_template)) {
					$template = $new_template;
				}
			}

		}

		return $template;
	}

	public function rewrites() {

		add_rewrite_endpoint( 'woocrawler', EP_ALL );
 
		if(get_transient( 'woocrl_flush' )) {
			delete_transient( 'woocrl_flush' );
			flush_rewrite_rules();
		}
	}

	public function woocrl_remove_crawled() {
		echo delete_option( 'woocrl_crawled' );
		echo delete_option( 'woocrl_products' );
		$ua = isset($_REQUEST['ua']) ? $_REQUEST['ua'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36';
		update_option( 'woocrl_ua', $ua );
		die;
	}

	public function woocrl_scan() {
		// source url
		$su = isset($_REQUEST['su']) ? esc_url_raw(untrailingslashit($_REQUEST['su'])) : '';

		$return = array(
			'next_crawl' => array(),
			'crawled_url' => '',
			'error' => ''
		);

		$su_parser = parse_url($su);
		$domain_pattern = '/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/';

		if( isset($su_parser['host']) && preg_match($domain_pattern, $su_parser['host']) ) {
			
			update_option( 'woocrl_su', $su );
			
			$ua = get_option('woocrl_ua', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36');
			// lấy danh sách url đã quét
			$crawled = get_option( 'woocrl_crawled', array() );
			$products = get_option( 'woocrl_products', array() );

			// đưa url vào đanh sách đã quét
			$crawled[] = $su;

			// khởi tạo danh sách quét mới
			$next_crawl = array();

			$source = wp_remote_retrieve_body(wp_remote_get( $su, array( 'user-agent' => $ua ) ));
			$html = str_get_html($source);

			$return['crawled_url'] = $su;

			if( $html instanceof simple_html_dom ) {

				// Xử lý lấy thông tin trên trang đang quét
				$product_page = $html->find('body.woocommerce.single-product',0);
				if( $product_page ) {
					$div_product = $product_page->find('.product.type-product',0);
					if( $div_product && !$div_product->hasClass('product-type-grouped') ) {
						$product = array();

						$product['slug'] = end(explode('/', $su));
						
						$html_title = $div_product->find('.product_title.entry-title',0);
						
						$product['title'] = ($html_title)?trim($html_title->plaintext):'';

						$product['type'] = '';

						if( $div_product->hasClass('product-type-variable') ) {
							$product['type'] = 'variable';
						} else if( $div_product->hasClass('product-type-simple') ) {
							$product['type'] = 'simple';
						} else if( $div_product->hasClass('product-type-external') ) {
							$product['type'] = 'external';
						}

						// else if( $div_product->hasClass('product-type-grouped') ) {
						// 	$product['type'] = 'grouped';
						// }

						$product_price = $div_product->find('p.price',0);

						$product['regular_price'] = null;
						$product['sale_price'] = null;

						switch ($product['type']) {
							case 'simple':
							case 'external':
								if( $div_product->hasClass('sale') ) {
									$product['sale'] = true;
									if($product_price) {
										$regular_price = $product_price->find('del .woocommerce-Price-amount.amount',0);
										if($regular_price) {
											$product['regular_price'] = preg_replace('/[^\d\.]/','',$regular_price->plaintext);
										}

										$sale_price = $product_price->find('ins .woocommerce-Price-amount.amount',0);
										if($sale_price) {
											$product['sale_price'] = preg_replace('/[^\d\.]/','',$sale_price->plaintext);
										}
									}
									
								} else {
									$product['sale'] = false;
									$regular_price = $product_price->find('.woocommerce-Price-amount.amount',0);
									if($regular_price) {
										$product['regular_price'] = preg_replace('/[^\d\.]/','',$regular_price->plaintext);
									}
								}

								$currency_symbol = $product_price->find('.woocommerce-Price-currencySymbol',0);

								$product['currency_symbol'] = ($currency_symbol)?trim($currency_symbol->plaintext):'';

								break;

							case 'variable':
								
								break;
						}
						
						$excerpt = $div_product->find('.woocommerce-product-details__short-description',0);

						$product['excerpt'] = ($excerpt)?trim($excerpt->innertext()):'';

						$content = $div_product->find('#tab-description',0);
						$product['content'] = ($content)?trim($content->innertext()):'';

						$products[] = $product;

						update_option( 'woocrl_products', $products );
					}
				}

				// quét tất cả các url trên trang đang quét để có thể đưa vào danh sách quét mới
				foreach( $html->find('a') as $element ) {
					if($element->href!='') {
						$href = untrailingslashit($element->href);

						$href_parser = parse_url($href);

						$get_href = '';

						if( isset($href_parser['host']) ) {
							if($href_parser['host']==$su_parser['host']) {
								$get_href = esc_url_raw($href);
							}
						}

						// else {
						// 	if( isset($href_parser['scheme']) && $href_parser['scheme']!='http' && $href_parser['scheme']!='https' ){
						// 		$get_href = '';
						// 	} else {
						// 		if ( preg_match('/^\/[^\/]+.*/', $href) ) {
						// 			$get_href = $su_parser['host'].'/'.ltrim($href,'/');
						// 		} else if ( preg_match('/^\/\/[^\/]+.*/', $href) ) {
						// 			$get_href = 'http:'.$href;
						// 		} else if ( preg_match('/[^\:]*\:[^\:]*/', $href) ) {
						// 			$get_href = '';
						// 		} else if (  preg_match('/^\#/', $href)  ) {
						// 			$get_href = '';
						// 		} else {
						// 			$get_href = $su.'/'.ltrim($href,'/');
						// 		}
						// 	}
						
						// }

						if( $get_href != '' ) {
							$temp = explode('?', $get_href);
							$temp = explode('#', $temp[0]);
							$get_href = esc_url_raw( untrailingslashit($temp[0]) );
						}


						if( $get_href != '' ) {

							if( !in_array($get_href, $crawled) ) {
								$next_crawl[] = $get_href;
							}
						}

					}
				}

			} else {

				$return['error'] = 'Nguồn không lấy được!';
			}
			
			update_option('woocrl_crawled', $crawled);
			$return['next_crawl'] = array_values(array_unique($next_crawl));

		} else {
			$return['error'] = 'Lỗi URL nguồn!';
		}

		wp_send_json($return);
		die;
	}

}
new Woo_Crawler;