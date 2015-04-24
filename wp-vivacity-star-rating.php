<?php
/*
  Plugin Name: WP Vivacity Star Rating
  Description: A very effective and User Friendly Star Rating System which allow users to give star rating to Post and Pages.
  Version: 1.0
  Author: Vivacity Infotech
  Author URI: http://vivacityinfotech.net/
  Author Email: support@vivacityinfotech.net
  Text Domain: wp-vivacity-star-rating
  Domain Path: /languages/
 */
 
 
 /*
Copyright 2014  Vivacity InfoTech Pvt. Ltd.  (email : support@vivacityinfotech.net)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
function wpvisr_activation()
{
    global $wpdb;
    $query="CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."wpvisr_votes`  (
	`post_id` INT(11) NULL DEFAULT NULL,
	`user_id` TINYTEXT NULL COLLATE 'utf8_unicode_ci',
	`points` INT(11) NULL DEFAULT NULL 
)
COLLATE='utf8_unicode_ci'
ENGINE=MyISAM;
";
    $wpdb->query($query);
    $query="CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."wpvisr_rating` (
	`post_id` INT(11) NOT NULL,
	`votes` INT(11) NOT NULL,
	`points` INT(11) NOT NULL
)
COLLATE='utf8_unicode_ci'
ENGINE=MyISAM;
";
    $wpdb->query($query);
    $list=wpvisr_get_post_type();
    foreach ($list as $list_)
    {
        $def_types[$list_]=0;
    }
    $default_options=array("shape"=>"s", "color"=>"y", "where_to_show"=>$def_types, "position"=>"before", "show_vote_count"=>"1", "activated"=>"0", "scale"=>"5", "alignment"=>"center", "allow_guest_vote"=>"0");
    add_option('wpvisr_settings', json_encode($default_options));
    add_option('wpvisr_version', '1.1');
}
/*Hook To Register plugin*/
register_activation_hook(__FILE__, 'wpvisr_activation');

/*Adding Plugin Menu In the Dashboard*/

add_action('admin_menu', 'wpvisr_menu');

function wpvisr_menu()
{
    add_menu_page( __('WP Vivacity Star Rating', 'wp-vivacity-star-rating') , 'WP Vivacity Star Rating', 'manage_options', 'wpvisr_options', 'wpvisr_options_page',plugin_dir_url( __FILE__ ) . 'images/star-image.png');
}

/*Including the Theme Options File*/
function wpvisr_options_page()
{
    require_once (plugin_dir_path(__FILE__).'/vivacity-star-rating-options.php');
}

/*Function For Language Translation*/
function wpvisr_action_init()
{
// Localization
load_plugin_textdomain('wp-vivacity-star-rating', false, dirname(plugin_basename(__FILE__)). '/languages');
}

// Add actions
add_action('init', 'wpvisr_action_init');
// Add Files 
function wpvisr_script_file_1() 
{
	wp_enqueue_script('jquery');
   wp_enqueue_style('wpvisr_style', plugins_url('/css/wpvisr_style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'wpvisr_script_file_1');
 
function wpvisr_script_file_2() 
{
	wp_enqueue_script('wpvisr_script', plugins_url('/js/wpvisr_script.js', __FILE__), array('jquery'), NULL);
} 
add_filter('the_content', 'wpvisr_script_file_2');

/*Code for filtering post content for adding Stars Rating*/
$theme_options = wpvisr_options();
//print_r($theme_options);
if($theme_options['activated']==1)
{
	add_filter('the_content','wpvisr_content_filter',15);
}

function wpvisr_content_filter($content)
{
	 $options=wpvisr_options();
	 //print_r($options);
    $list=wpvisr_get_post_type();
    global $post, $wpdb;
    $disable_rating = get_post_meta($post->ID, '_wpvisr_disable', true);
    
    foreach ($list as $list_)
    {
        if (is_singular($list_)&&$options['where_to_show'][$list_]&&$disable_rating!='1')
        {
				if ($options['position']=='before')
            {
                $content=wpvisr_rating().$content;
            }
            elseif ($options['position']=='after')
            {
                $content .= wpvisr_rating();
            }
            break;
        }
    }
    return $content;
}

function wpvisr_rating()
{
    global $post, $current_user, $wpdb;
    $query="select `votes`, `points` from `".$wpdb->prefix."wpvisr_rating` where `post_id`='$post->ID';";
    $popularity=$wpdb->get_results($query, ARRAY_N);
    if (count($popularity)>0)
    {
        $votes=$popularity[0][0];
        $points=$popularity[0][1];
    }
    else
    {
        $votes=0;
        $points=0;
    }
   
    $options=wpvisr_options();
   
    if (is_user_logged_in()==1)
    {
        $query="select * from `".$wpdb->prefix."wpvisr_votes` where `post_id`='$post->ID' and `user_id`='$current_user->ID';";
        $voted=$wpdb->get_results($query, ARRAY_N);
        if (count($voted)>0)
        {
            $results='<div id="wpvisr_container"><div class="wpvisr_visual_container">'.wpvisr_show_voted($votes, $points, $options['show_vote_count']).'</div></div>';
            wp_localize_script('wpvisr_script', 'wpvisr_script_ajax_object', array('ajax_url'=>admin_url('admin-ajax.php'), 'scale'=>$options['scale'], 'wpvisr_type'=>$options['color'].$options['shape'], 'rating_working'=>'false', 'post_id'=>$post->ID));
            return $results;
        }
        else
        {
            $results='<div id="wpvisr_container"><div class="wpvisr_visual_container" id="wpvisr_container_'.$post->ID.'">'.wpvisr_show_voting($votes, $points, $options['show_vote_count']).'</div></div>';
            wp_localize_script('wpvisr_script', 'wpvisr_script_ajax_object', array('ajax_url'=>admin_url('admin-ajax.php'), 'scale'=>$options['scale'], 'wpvisr_type'=>$options['color'].$options['shape'], 'rating_working'=>'false', 'post_id'=>$post->ID));
            return $results;
        }
    }
    else if ($options['allow_guest_vote']&&filter_var(wpvisr_get_user_ip(), FILTER_VALIDATE_IP))
    {
        $query="select * from `".$wpdb->prefix."wpvisr_votes` where `post_id`='$post->ID' and `user_id`='".wpvisr_get_user_ip()."';";
        $voted=$wpdb->get_results($query, ARRAY_N);
        if (count($voted)>0)
        {
            $results='<div id="wpvisr_container"><div class="wpvisr_visual_container">'.wpvisr_show_voted($votes, $points, $options['show_vote_count']).'</div></div>';
            wp_localize_script('wpvisr_script', 'wpvisr_script_ajax_object', array('ajax_url'=>admin_url('admin-ajax.php'), 'scale'=>$options['scale'], 'wpvisr_type'=>$options['color'].$options['shape'], 'rating_working'=>'false', 'post_id'=>$post->ID));
            return $results;
        }
        else
        {
            $results='<div id="wpvisr_container"><div class="wpvisr_visual_container" id="wpvisr_container_'.$post->ID.'">'.wpvisr_show_voting($votes, $points, $options['show_vote_count']).'</div></div>';
            wp_localize_script('wpvisr_script', 'wpvisr_script_ajax_object', array('ajax_url'=>admin_url('admin-ajax.php'), 'scale'=>$options['scale'], 'wpvisr_type'=>$options['color'].$options['shape'], 'rating_working'=>'false', 'post_id'=>$post->ID));
            return $results;
        }
    }
    else
    {
        wp_localize_script('wpvisr_script', 'wpvisr_script_ajax_object', array('ajax_url'=>admin_url('admin-ajax.php'), 'scale'=>$options['scale'], 'wpvisr_type'=>$options['color'].$options['shape'], 'rating_working'=>false, 'post_id'=>$post->ID));
        $results='<div id="wpvisr_container"><div class="wpvisr_visual_container">'.wpvisr_show_voted($votes, $points, $options['show_vote_count']).'</div></div>';
        return $results;
    }
}

/*Adding Rating Enable Option In Post Edit Screen*/

add_action('post_submitbox_misc_actions', 'add_disable_wpvisr_checkbox');
function add_disable_wpvisr_checkbox()
{
    global $post;
    $type=get_post_type($post->ID);
    $disable_rating=get_post_meta($post->ID, '_wpvisr_disable', true);
    ?>
    <div class="misc-pub-section">
        <input id="wpvisr_disable_rating" type="checkbox" name="wpvisr_disable_rating"  value="<?php echo $disable_rating; ?>" <?php checked($disable_rating, 1, true); ?>>
        <label for="wpvisr_enable_rating">Disable Rating For This Entry </label></div>
    <?php
}

add_filter('wp_insert_post_data', 'wpvisr_filter_handler', '99', 2);

function wpvisr_filter_handler($data, $postarr)
{

    if (isset($_POST['wpvisr_disable_rating']))
    {
        update_post_meta($postarr['ID'], '_wpvisr_disable', '1');
    }
    else
    {
        delete_post_meta($postarr['ID'], '_wpvisr_disable');
    }
    return $data;
}


function wpvisr_show_voted($votes, $points, $show_vc){
	 $options=  wpvisr_options();
    $wpvisr_type=$options['color'].$options['shape'];
    if ($votes>0)
    {
        $rate=$points/$votes;
    }
    else
    {
        $rate=0;
        $votes=0;
    }
    $html='<div id="wpvisr_shapes">';
    for ($i=1; $i<=$options['scale']; $i++)
    {
        if ($rate>=($i-0.25))
        {
            $class='wpvisr_'.$wpvisr_type.'_full_voted';
        }
        elseif ($rate<($i-0.25)&&$rate>=($i-0.75))
        {
            $class='wpvisr_'.$wpvisr_type.'_half_voted';
        }
        else
        {
            $class='wpvisr_'.$wpvisr_type.'_empty';
        }
        $html .= '<span class="wpvisr_rating_piece '.$class.'"></span> ';
    }
    $html.='</div>';
    if ($show_vc)
    {
        $html .= '<span id="wpvisr_votes">'.$votes.' votes </span>';
    }

    return $html;
		
}

function wpvisr_show_voting($votes, $points, $show_vc){
			
	 $options=wpvisr_options();
    $wpvisr_type=$options['color'].$options['shape'];
    //print_r($wpvisr_type);
    if ($votes>0)
    {
        $rate=$points/$votes;
    }
    else
    {
        $rate=0;
        $votes=0;
    }
    $html='<div id="wpvisr_shapes">';
    for ($i=1; $i<=$options['scale']; $i++)
    {
        if ($rate>=($i-0.25))
        {
            $class='wpvisr_'.$wpvisr_type.'_full_voting';
        }
        elseif ($rate<($i-0.25)&&$rate>=($i-0.75))
        {
            $class='wpvisr_'.$wpvisr_type.'_half_voting';
        }
        else
        {
            $class='wpvisr_'.$wpvisr_type.'_empty';
        }
        $html .= '<span id="wpvisr_piece_'.$i.'" class="wpvisr_rating_piece '.$class.'"></span> ';
    }
    $html.='</div>';
    //echo $show_vc; 
    if ($show_vc)
    {
        $html .= '<span id="wpvisr_votes">'.$votes.' Votes</span>';
    }
    return $html;			
			
}

/*Function For fetching Plugin Settings Option*/
function wpvisr_options()
{
    $post_list=wpvisr_get_post_type();
    foreach ($post_list as $list_)
    {
        $post_types[$list_]=0;
    }
    $default_options=array("shape"=>"s", "color"=>"y", "where_to_show"=>$post_types, "position"=>"before", "show_vote_count"=>"1", "activated"=>"0", "scale"=>"5", "alignment"=>"center", "allow_guest_vote"=>"0");
    $options=get_option('wpvisr_settings', 'undef');
	
    if ($options!='undef')
    {
        $options=json_decode($options, true);
        $diff=array_diff_key($default_options, $options);
        if (count($diff)>0)
        {
            $options=array_merge($options, $diff);
        }
    }
    else
    {
        $options=$default_options;
    }
    return $options;
}

/*Function To Get the Post Type*/
function wpvisr_get_post_type()
{
    $types=array("post", "page");
    $post_types=get_post_types(array('public'=>true, '_builtin'=>false), 'objects', 'and');
    foreach ($post_types as $post_type)
    {
        $types[]=$post_type->rewrite['slug'];
    }
    //print_r($types);
    return $types;
}

/*Function to get Post Type in options Settings*/
function wpvisr_get_post_types_for()
{
    $options = wpvisr_options();
    $post_types=get_post_types(array('public'=>true, '_builtin'=>false), 'objects', 'and');
    $result='<table><tr><td class="wpvisr_cb_labels">Posts</td><td><input type="checkbox" name="post" id="post" value="'.$options['where_to_show']['post'].'" '.checked($options['where_to_show']['post'], 1, false).'></td></tr><tr><td class="wpvisr_cb_labels">Pages</td><td><input type="checkbox" name="page" id="page" value="'.$options['where_to_show']['page'].'" '.checked($options['where_to_show']['page'], 1, false).'></td></tr>';
    foreach ($post_types as $post_type)
    {
        $result.= '<tr><td class="wpvisr_cb_labels">'.$post_type->labels->name.'</td><td><input type="checkbox" name="'.$post_type->rewrite['slug'].'" id="'.$post_type->rewrite['slug'].'" value="'.$options['where_to_show'][$post_type->rewrite['slug']].'" '.checked($options['where_to_show'][$post_type->rewrite['slug']], 1, false).'></td></tr>';
    }
    $result.="</table>";
    return $result;
}

/*Function To save the Plugin Options*/
function wpvisr_save_options() {
$theme_options = wpvisr_options();
$current_json = json_encode($theme_options);
	//echo "<pre>";
	if (isset($_POST['wpvisr_shape'])||isset($_POST['wpvisr_color'])||isset($_POST['wpvisr_position'])||isset($_POST['wpvisr_alignment'])||isset($_POST['wpvisr_show_vote_count'])||isset($_POST['wpvisr_activated'])||isset($_POST['wpvisr_allow_guest_vote'])||isset($_POST['scale']))
    	{
			if(isset($_POST['wpvisr_shape']))
				{
					switch ($_POST['wpvisr_shape']) 
					{
						case 'c' :
						{
							$options['shape'] = 'c';
							break;
						}
						 case 's' :
                    {
                       $options['shape']='s';
                        break;
                    }
                    case 'h' :
                    {
                       $options['shape']='h';
                        break;
                    }
						 default:
                     {
                        $options['shape']=$theme_options['shape'];
                        break;
                     }
					}
				}
				/*Colour*/
				if(isset($_POST['wpvisr_color']))
				{
					switch ($_POST['wpvisr_color']) 
					{
						case 'p' :
						{
							$options['color'] = 'p';
							break;
						}
						 case 'b' :
                    {
                       $options['color']='b';
                        break;
                    }
                    case 'y' :
                    {
                       $options['color']='y';
                        break;
                    }
                    case 'r' :
                    {
                       $options['color']='r';
                        break;
                    }
                    case 'g' :
                    {
                       $options['color']='g';
                        break;
                    }
						default:
                    {
                        $options['color']=$theme_options['color'];
                        break;
                    }
					}
				
				}
				/*Position*/
				if(isset($_POST['wpvisr_position']))
				{
					switch ($_POST['wpvisr_position']) 
					{
						case 'before' :
						{
							$options['position'] = 'before';
							break;
						}
						 case 'after' :
                    {
                       $options['position']='after';
                        break;
                    }
                    default:
                    {
                        $options['position']=$theme_options['position'];
                        break;
                    }
					}
				
				}
				
				/*Alignment*/
				if(isset($_POST['wpvisr_alignment']))
				{
					switch ($_POST['wpvisr_alignment']) 
					{
						case 'center' :
						{
							$options['alignment'] = 'center';
							break;
						}
						 case 'right' :
                    {
                       $options['alignment']='right';
                        break;
                    }
                     case 'left' :
                    {
                       $options['alignment']='left';
                        break;
                    }
                    default:
                    {
                        $options['alignment']=$theme_options['alignment'];
                        break;
                    }
					}
				
				}
				
				/*Show Vote Count*/
				
				if(isset($_POST['wpvisr_show_vote_count']))
				{
					$options['show_vote_count'] = 1;
				}
				else 
				{
					$options['show_vote_count'] = 0;
				}
				
				/*Activated*/
				if(isset($_POST['wpvisr_activated']))
				{
					$options['activated'] = 1;
				}
				else 
				{
					$options['activated'] = 0;
				}
				
				/*Scale*/
				 
	        if (isset($_POST['wpvisr_scale']))
   	     {
      	      if ($_POST['wpvisr_scale']>=3&&$_POST['wpvisr_scale']<=10)
         	   {
            	    $options['scale']=$_POST['wpvisr_scale'];
            	}
            else
            	{
               	 $options['scale']=$theme_options['scale'];
            	}
        	  }
        
        /*Allow guests to vote*/
        if (isset($_POST['wpvisr_allow_guest_vote']))
        {
            $options['allow_guest_vote']='1';
        }
        else
        {
            $options['allow_guest_vote']='0';
        }
              
        /*Where Do we want to show stars*/
			$post_lists=wpvisr_get_post_type();         
         //print_r($post_lists);
        	foreach($post_lists as $post_list)
        		{	
        			$deftypes[$post_list]=0;
        			if(isset($_POST[$post_list]))
        				{
        					$options['where_to_show'][$post_list] =1;
        				}
        				else 
        				{
        					$options['where_to_show'][$post_list] =0;
        				}
        		} 
        		
        $default_options=array("shape"=>"s", "color"=>"y", "where_to_show"=>$def_types, "position"=>"before", "show_vote_count"=>"1", "activated"=>"0", "scale"=>"5", "alignment"=>"center", "allow_guest_vote"=>"0");
        
        $diff=array_diff_key($default_options, $options);
    
        if (count($diff)>0)
        {
            $options=array_merge($options, $diff);
        }
        
        $options=json_encode($options);
        
        if($current_json!=$options)
	        	{
       	 		update_option('wpvisr_settings', $options);
   	   	 }  
		}
		
}
/*Function To Get the User IP*/
function wpvisr_get_user_ip() {
		$ip = $_SERVER['REMOTE_ADDR'];     
        if($ip){
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            return $ip;
        }
        // There might not be any data
        return false;
    }
  

/*Function For Inserting Rating*/

function wpvisr_star_rating()
{
    global $current_user, $wpdb;
    $options=wpvisr_options();
	if ($options['activated']==1)
    {
        if (isset($_POST['points'])&&isset($_POST['post_id'])) // key parameters are set
        {
             $post_id=(int) esc_sql($_POST['post_id']);
          	 $points_=(int) esc_sql($_POST['points']);
          	
            if ($points_>=1&&$points_<=$options['scale'])
            {
                if (is_user_logged_in()==1) // user is logged in
                {
                    $query="select * from `".$wpdb->prefix."posts` where `ID`='$post_id';";
                    $post_exists=$wpdb->get_results($query, ARRAY_N);
                    if (count($post_exists)>0) // post exists
                    {
                        $query="select * from `".$wpdb->prefix."wpvisr_votes` where `post_id`='$post_id' and `user_id`='$current_user->ID';";
                        $voted=$wpdb->get_results($query, ARRAY_N);
                        if (count($voted)>0)  // already voted
                        {
                            $response=json_encode(array('status'=>2));
                        }
                        else // haven't voted yet 
                        {
                            $wpdb->query("INSERT INTO `".$wpdb->prefix."wpvisr_votes` (`post_id`, `user_id`, `points`) VALUES ('$post_id', '$current_user->ID', '$points_');");
                            $query="select `votes`, `points` from `".$wpdb->prefix."wpvisr_rating` where `post_id`='$post_id';";
                            $popularity=$wpdb->get_results($query, ARRAY_N);
                            if (count($popularity)>0)
                            {
                                $votes=$popularity[0][0];
                                $points=$popularity[0][1];
                            }
                            else
                            {
                                $votes=0;
                                $points=0;
                            }
                            if ($votes==0||$points==0)
                            {
                                $wpdb->query("INSERT INTO `".$wpdb->prefix."wpvisr_rating` (`post_id`, `votes`, `points`) VALUES ('$post_id', '1', '$points_');");
                            }
                            else
                            {
                                $points=$points+$points_;
                                $votes=$votes+1;
                                $wpdb->query("UPDATE `".$wpdb->prefix."wpvisr_rating` set `votes`='$votes', `points`='$points' where `post_id`='$post_id';");
                            }
                            $query="select `votes`, `points` from `".$wpdb->prefix."wpvisr_rating` where `post_id`='$post_id';";
                            $popularity=$wpdb->get_results($query, ARRAY_N);
                            if (count($popularity)>0)
                            {
                                $votes=$popularity[0][0];
                                $points=$popularity[0][1];
                            }
                            else
                            {
                                $votes=0;
                                $points=0;
                            }
                            $html=wpvisr_show_voted($votes, $points, $options['show_vote_count']);
                            $response=json_encode(array('status'=>1, 'html'=>$html));
                        }
                    }
                    else
                    {
                        $response=json_encode(array('status'=>3)); // post doesn't exist
                    }
                }
                else if ($options['allow_guest_vote']&&filter_var(wpvisr_get_user_ip(), FILTER_VALIDATE_IP))
                {
                    $query="select * from `".$wpdb->prefix."posts` where `ID`='$post_id';";
                    $post_exists=$wpdb->get_results($query, ARRAY_N);
                    if (count($post_exists)>0) // post exists
                    {
                        $query="select * from `".$wpdb->prefix."wpvisr_votes` where `post_id`='$post_id' and `user_id`='".wpvisr_get_user_ip()."';";
                        $voted=$wpdb->get_results($query, ARRAY_N);
                        if (count($voted)>0)  // already voted
                        {
                            $response=json_encode(array('status'=>2));
                        }
                        else // haven't voted yet 
                        {
                            $wpdb->query("INSERT INTO `".$wpdb->prefix."wpvisr_votes` (`post_id`, `user_id`, `points`) VALUES ('$post_id', '".wpvisr_get_user_ip()."', '$points_');");
                            $query="select `votes`, `points` from `".$wpdb->prefix."wpvisr_rating` where `post_id`='$post_id';";
                            $popularity=$wpdb->get_results($query, ARRAY_N);
                            if (count($popularity)>0)
                            {
                                $votes=$popularity[0][0];
                                $points=$popularity[0][1];
                            }
                            else
                            {
                                $votes=0;
                                $points=0;
                            }
                            if ($votes==0||$points==0)
                            {
                                $wpdb->query("INSERT INTO `".$wpdb->prefix."wpvisr_rating` (`post_id`, `votes`, `points`) VALUES ('$post_id', '1', '$points_');");
                            }
                            else
                            {
                                $points=$points+$points_;
                                $votes=$votes+1;
                                $wpdb->query("UPDATE `".$wpdb->prefix."wpvisr_rating` set `votes`='$votes', `points`='$points' where `post_id`='$post_id';");
                            }
                            $query="select `votes`, `points` from `".$wpdb->prefix."wpvisr_rating` where `post_id`='$post_id';";
                            $popularity=$wpdb->get_results($query, ARRAY_N);
                            if (count($popularity)>0)
                            {
                                $votes=$popularity[0][0];
                                $points=$popularity[0][1];
                            }
                            else
                            {
                                $votes=0;
                                $points=0;
                            }
                            $html=wpvisr_show_voted($votes, $points, $options['show_vote_count']);
                            $response=json_encode(array('status'=>1, 'html'=>$html));
                        }
                    }
                    else
                    {
                        $response=json_encode(array('status'=>3)); // post doesn't exist
                    }
                }
                else
                {
                    $response=json_encode(array('status'=>4)); // user isn't logged in
                }
            }
            else
            {
                $response=json_encode(array('status'=>5));  // key parameters aren't set
            }
        }
        else
        {
            $response=json_encode(array('status'=>6));  // key parameters aren't set
        }
    }
    else
    {
        $response=json_encode(array('status'=>7));  // rating isn't active
    }
    echo $response;
    if (isset($_POST['action']))
    {
        die();
    }
}

add_action('wp_ajax_wpvisr_star_rating', 'wpvisr_star_rating');
add_action('wp_ajax_nopriv_wpvisr_star_rating', 'wpvisr_star_rating');

/*Function For Resetting Votes*/
 function wpvisr_reset_votes() {
 	 global $wpdb;
    $query="TRUNCATE TABLE `".$wpdb->prefix."wpvisr_votes` ;";
    $wpdb->query($query);
    $query="TRUNCATE TABLE `".$wpdb->prefix."wpvisr_rating`;";
    $wpdb->query($query);
    echo "<div class='updated'><p><?php _e('All votes were cleared.','wp-vivacity-star-rating');?></p></div>";
 }

/*Function For Adding Custom Dashboard Icon*/
function replace_admin_menu_icons_css() {
    ?>
    <style>
        #adminmenu #toplevel_page_wpvisr_options div.wp-menu-image {
    		background: none !important;
}
    </style>
    <?php
}

add_action( 'admin_head', 'replace_admin_menu_icons_css' );

/*Loading JS file For admin*/
function my_enqueue($hook) 
{
    wp_enqueue_script( 'jquery-ui-core');
    wp_enqueue_script('jquery-ui-tabs');
    wp_enqueue_script('farbtastic');
    wp_enqueue_style('jquery-ui-wvsr', plugins_url('/css/jquery-ui-wvsr.css', __FILE__));
    wp_enqueue_style('farbtastic');
	wp_enqueue_style('wpvisr_style', plugins_url('/css/wpvisr_style.css', __FILE__));
}
add_action( 'admin_enqueue_scripts', 'my_enqueue' );
?>