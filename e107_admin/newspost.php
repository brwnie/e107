<?php
/*
 * e107 website system
 *
 * Copyright (C) 2001-2008 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * News Administration
 *
 * $Source: /cvs_backup/e107_0.8/e107_admin/newspost.php,v $
 * $Revision: 1.20 $
 * $Date: 2009-01-13 17:09:30 $
 * $Author: secretr $
*/
require_once("../class2.php");

if (!getperms("H"))
{
	header("location:".e_BASE."index.php");
	exit;
}


$newspost = new admin_newspost(e_QUERY);
function headerjs()
{
  	global $newspost;

	require_once(e_HANDLER.'js_helper.php');
	$ret = "
		<script type='text/javascript'>
			if(typeof e107Admin == 'undefined') var e107Admin = {}

			/**
			 * OnLoad Init Control
			 */
			e107Admin.initRules = {
				'Helper': true,
				'AdminMenu': false
			}
		</script>
		<script type='text/javascript' src='".e_FILE_ABS."jslib/core/admin.js'></script>
	";
	$ret .= $newspost->_cal->load_files();

   	return $ret;
}
$e_sub_cat = 'news';
$e_wysiwyg = "data,news_extended";

// -------- Presets. ------------  // always load before auth.php
require_once(e_HANDLER."preset_class.php");
$pst = new e_preset;
$pst->form = "dataform"; // form id of the form that will have it's values saved.
$pst->page = "newspost.php?create"; // display preset options on which page(s).
$pst->id = "admin_newspost";
// ------------------------------


require_once("auth.php");
$pst->save_preset('news_datestamp'); // save and render result using unique name. Don't save item datestamp

require_once(e_HANDLER."userclass_class.php");

require_once(e_HANDLER."ren_help.php");
require_once(e_HANDLER."form_handler.php");
require_once(e_HANDLER."file_class.php");
require_once (e_HANDLER."message_handler.php");

$fl = new e_file;
$rs = new form();
$frm = new e_form(true); //enable inner tabindex counter
$emessage = &eMessage::getInstance();



if (e_QUERY)
{
  $tmp = explode(".", e_QUERY);
  $action = $tmp[0];
  $sub_action = varset($tmp[1],'');
  $id = intval(varset($tmp[2],0));
  $sort_order = varset($tmp[2],'desc');
  $from = intval(varset($tmp[3],0));
  unset($tmp);
}

$from = ($from ? $from : 0);


// ##### Main loop -----------------------------------------------------------------------------------------------------------------------

if(isset($_POST['news_userclass']))
{
	unset($temp);
	foreach ($_POST['news_userclass'] as $k => $v)
	{
		$temp[] = intval($k);
	}
	$_POST['news_class'] = implode(",", $temp);
	unset($temp);
	unset($_POST['news_userclass']);
}


/*
 * Observe for delete action
 */
$newspost->observer();




// required.
if (isset($_POST['preview']))
{
	$_POST['news_title'] = $tp->toDB($_POST['news_title']);
	$_POST['news_summary'] = $tp->toDB($_POST['news_summary']);
	$newspost->preview_item($id);
}





if (!e_QUERY || $action == "main")
{
  $newspost->show_existing_items();
}


if ($action == "create")
{
  $preset = $pst->read_preset("admin_newspost");  //only works here because $_POST is used.

  if ($sub_action == "edit" && !$_POST['preview'] && !$_POST['submit_news'])
  {
	if ($sql->db_Select("news", "*", "news_id='{$id}' "))
	{
		$row = $sql->db_Fetch();
		extract($row);
		$_POST['news_title'] = $news_title;
		$_POST['data'] = $news_body;
		$_POST['news_author'] = $row['news_author'];
		$_POST['news_extended'] = $news_extended;
		$_POST['news_allow_comments'] = $news_allow_comments;
		$_POST['news_class'] = $news_class;
		$_POST['news_summary'] = $news_summary;
		$_POST['news_sticky'] = $news_sticky;
		$_POST['news_datestamp'] = ($_POST['news_datestamp']) ? $_POST['news_datestamp'] : $news_datestamp;

		$_POST['cat_id'] = $news_category;
		$_POST['news_start'] = $news_start;
		$_POST['news_end'] = $news_end;
		$_POST['comment_total'] = $sql->db_Count("comments", "(*)", " WHERE comment_item_id='$news_id' AND comment_type='0' ");
		$_POST['news_rendertype'] = $news_render_type;
		$_POST['news_thumbnail'] = $news_thumbnail;
	}
  }

  $newspost->create_item($sub_action, $id);
}



if ($action == "cat")
{
  $newspost->show_categories($sub_action, $id);
}



if ($action == "sn")
{
  $newspost->submitted_news($sub_action, $id);
}



if ($action == "pref")
{
  $newspost->show_news_prefs($sub_action, $id);
}


echo "
<script type=\"text/javascript\">
function fclear() {
	document.getElementById('dataform').data.value = \"\";
	document.getElementById('dataform').news_extended.value = \"\";
}
</script>\n";

require_once("footer.php");
exit;





class admin_newspost
{
	var $_request = array();
	var $_cal = array();
	var $_frm = array();

	function admin_newspost($qry)
	{

		$this->parseRequest($qry);

		require_once(e_HANDLER."calendar/calendar_class.php");
		$this->_cal = new DHTML_Calendar(true);

	}

	function parseRequest($qry)
	{
		$tmp = explode(".", $qry);
		$action = varsettrue($tmp[0], 'main');
		$sub_action = varset($tmp[1],'');
		$id = isset($tmp[2]) && is_numeric($tmp[2]) ? intval($tmp[2]) : 0;
		$sort_order = isset($tmp[2]) && !is_numeric($tmp[2]) ? $tmp[2] : 'desc';
		$from = intval(varset($tmp[3],0));
		unset($tmp);

		$this->_request = array($action, $sub_action, $id, $sort_order, $from);
	}

	function getAction()
	{
		return $this->_request[0];
	}

	function getSubAction()
	{
		return $this->_request[1];
	}

	function getId()
	{
		return $this->_request[2];
	}

	function getSortOrder()
	{
		return $this->_request[3];
	}

	function getFrom()
	{
		return $this->_request[4];
	}

	function clear_cache()
	{
		$e107 = &e107::getInstance();
		$e107->ecache->clear("news.php");
		$e107->ecache->clear("othernews");
		$e107->ecache->clear("othernews2");
	}

	function observer()
	{
		if(isset($_POST['delete']) && is_array($_POST['delete']))
		{
			$this->_observe_delete();
		}
		elseif(isset($_POST['submit_news']))
		{
			$this->_observe_submit_item($this->getSubAction(), $this->getId());
		}
		elseif(isset($_POST['create_category']))
		{
			$this->_observe_create_category();
		}
		elseif(isset($_POST['update_category']))
		{
			$this->_observe_update_category();
		}
		elseif(isset($_POST['save_prefs']))
		{
			$this->_observe_save_prefs();
		}
		elseif(isset($_POST['submitupload']))
		{
			$this->_observe_upload();
		}
	}

	function _observe_delete()
	{
		global $admin_log;

		$tmp = array_keys($_POST['delete']);
		list($delete, $del_id) = explode("_", $tmp[0]);
		$del_id = intval($del_id);

		if(!$del_id) return false;

		$e107 = &e107::getInstance();

		switch ($delete) {
			case 'main':
				if ($e107->sql->db_Count('news','(*)',"WHERE news_id={$del_id}"))
				{
					$e107->e_event->trigger("newsdel", $del_id);
					if($e107->sql->db_Delete("news", "news_id={$del_id}"))
					{
						$admin_log->log_event('NEWS_01',$del_id,E_LOG_INFORMATIVE,'');
						$this->show_message(NWSLAN_31." #".$del_id." ".NWSLAN_32, E_MESSAGE_SUCCESS);
						$this->clear_cache();

						$data = array('method'=>'delete', 'table'=>'news', 'id'=>$del_id, 'plugin'=>'news', 'function'=>'delete');
						$this->show_message($e107->e_event->triggerHook($data), E_MESSAGE_ERROR);

						admin_purge_related("news", $del_id);
					}
				}
			break;

			case 'category':
					if ($e107->sql->db_Delete("news_category", "category_id={$del_id}"))
					{
						$admin_log->log_event('NEWS_02',$del_id,E_LOG_INFORMATIVE,'');
						$this->show_message(NWSLAN_33." #".$del_id." ".NWSLAN_32, E_MESSAGE_SUCCESS);
						$this->clear_cache();
					}
			break;

			case 'sn':
					if ($e107->sql->db_Delete("submitnews", "submitnews_id={$del_id}"))
					{
						$admin_log->log_event('NEWS_03',$del_id,E_LOG_INFORMATIVE,'');
						$this->show_message(NWSLAN_34." #".$del_id." ".NWSLAN_32);
						$this->clear_cache();
					}
			break;

			default:
				return  false;
		}

		return true;
	}

	function _observe_submit_item($sub_action, $id)
	{
		// ##### Format and submit item to DB
		global $admin_log;

		$e107 = &e107::getInstance();

		require_once(e_HANDLER."news_class.php");
		$ix = new news;

		if($_POST['news_start'])
		{
			$tmp = explode("/", $_POST['news_start']);
			$_POST['news_start'] = mktime(0, 0, 0, $tmp[1], $tmp[0], $tmp[2]);
		}
		else
		{
			$_POST['news_start'] = 0;
		}

		if($_POST['news_end'])
		{
			$tmp = explode("/", $_POST['news_end']);
			$_POST['news_end'] = mktime(0, 0, 0, $tmp[1], $tmp[0], $tmp[2]);
		}
		else
		{
			$_POST['news_end'] = 0;
		}

		$matches = array();
		if(preg_match('#(.*?)/(.*?)/(.*?) (.*?):(.*?):(.*?)$#', $_POST['news_datestamp'], $matches))
		{
			$_POST['news_datestamp'] = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[1], $matches[3]);
		}
		else
		{
			$_POST['news_datestamp'] = time();
		}

		if($_POST['update_datestamp'])
		{
			$_POST['news_datestamp'] = time();
		}

		if ($id && $sub_action != "sn" && $sub_action != "upload")
		{
			$_POST['news_id'] = $id;
		}
		else
		{
			$e107->sql->db_Update('submitnews', "submitnews_auth=1 WHERE submitnews_id ={$id}");
			$admin_log->log_event('NEWS_07',$id,E_LOG_INFORMATIVE,'');
		}
		if (!$_POST['cat_id'])
		{
			$_POST['cat_id'] = 1;
		}

        list($_POST['news_author'], $empty) = explode(chr(35), $_POST['news_author']);

		$this->show_message($ix->submit_item($_POST));
		unset($_POST['news_title'], $_POST['cat_id'], $_POST['data'], $_POST['news_extended'], $_POST['news_allow_comments'], $_POST['startday'], $_POST['startmonth'], $_POST['startyear'], $_POST['endday'], $_POST['endmonth'], $_POST['endyear'], $_POST['news_id'], $_POST['news_class']);
		$this->clear_cache();
	}

	function _observe_create_category()
	{
		global $admin_log;
		if ($_POST['category_name'])
		{
			$e107 = &e107::getInstance();
			if (empty($_POST['category_button']))
			{
				$handle = opendir(e_IMAGE."icons");
				while ($file = readdir($handle))
				{
					if ($file != "." && $file != ".." && $file != "/" && $file != "null.txt" && $file != "CVS") {
						$iconlist[] = $file;
					}
				}
				closedir($handle);
				$_POST['category_button'] = $iconlist[0];
			}
			else
			{
				$_POST['category_button'] = $tp->toDB($_POST['category_button']);
			}
			$_POST['category_name'] = $tp->toDB($_POST['category_name']);
			$e107->sql->db_Insert('news_category', "'0', '{$_POST['category_name']}', '{$_POST['category_button']}'");
			$admin_log->log_event('NEWS_04',$_POST['category_name'].', '.$_POST['category_button'],E_LOG_INFORMATIVE,'');
			$this->show_message(NWSLAN_35, E_MESSAGE_SUCCESS);
			$this->clear_cache();
		}
	}
	function _observe_update_category()
	{
		global $admin_log;
		if ($_POST['category_name'])
		{
			$e107 = &e107::getInstance();
			$category_button = $e107->tp->toDB(($_POST['category_button'] ? $_POST['category_button'] : ""));
			$_POST['category_name'] = $e107->tp->toDB($_POST['category_name']);
			$e107->sql->db_Update("news_category", "category_name='".$_POST['category_name']."', category_icon='".$category_button."' WHERE category_id=".intval($_POST['category_id']));
			$admin_log->log_event('NEWS_05',intval($_POST['category_id']).':'.$_POST['category_name'].', '.$category_button,E_LOG_INFORMATIVE,'');
			$this->show_message(NWSLAN_36, E_MESSAGE_SUCCESS);
			$this->clear_cache();
		}
	}

	function _observe_save_prefs()
	{
		global $pref, $admin_log;
		$temp = array();
		$temp['newsposts'] 				= intval($_POST['newsposts']);
	   	$temp['newsposts_archive'] 		= intval($_POST['newsposts_archive']);
		$temp['newsposts_archive_title'] = $tp->toDB($_POST['newsposts_archive_title']);
		$temp['news_cats'] 				= intval($_POST['news_cats']);
		$temp['nbr_cols'] 				= intval($_POST['nbr_cols']);
		$temp['subnews_attach'] 		= intval($_POST['subnews_attach']);
		$temp['subnews_resize'] 		= intval($_POST['subnews_resize']);
		$temp['subnews_class'] 			= intval($_POST['subnews_class']);
		$temp['subnews_htmlarea'] 		= intval($_POST['subnews_htmlarea']);
		$temp['news_subheader'] 		= $tp->toDB($_POST['news_subheader']);
		$temp['news_newdateheader'] 	= intval($_POST['news_newdateheader']);
		$temp['news_unstemplate'] 		= intval($_POST['news_unstemplate']);
		$temp['news_editauthor']		= intval($_POST['news_editauthor']);

		if ($admin_log->logArrayDiffs($temp, $pref, 'NEWS_06'))
		{
			save_prefs();		// Only save if changes
			$this->clear_cache();
			$this->show_message(NWSLAN_119, E_MESSAGE_SUCCESS);
		}
		else
		{
			$this->show_message(LAN_NEWS_47);
		}
	}

	function _observe_upload()
	{
		//$pref['upload_storagetype'] = "1";
		require_once(e_HANDLER."upload_handler.php");

		$uploaded = file_upload(e_IMAGE."newspost_images/");

		foreach($_POST['uploadtype'] as $key=>$uploadtype)
		{
			if($uploadtype == "thumb")
			{
				rename(e_IMAGE."newspost_images/".$uploaded[$key]['name'],e_IMAGE."newspost_images/thumb_".$uploaded[$key]['name']);
			}

			if($uploadtype == "file")
			{
				rename(e_IMAGE."newspost_images/".$uploaded[$key]['name'],e_FILE."downloads/".$uploaded[$key]['name']);
			}

			if ($uploadtype == "resize" && $_POST['resize_value'])
			{
				require_once(e_HANDLER."resize_handler.php");
				resize_image(e_IMAGE."newspost_images/".$uploaded[$key]['name'], e_IMAGE."newspost_images/".$uploaded[$key]['name'], $_POST['resize_value'], "copy");
			}
		}
	}

	function show_existing_items()
	{
		require_once(e_HANDLER."form_handler.php");
		$frm = new e_form(true); //enable inner tabindex counter

		$sort_order = $this->getSortOrder();
		if ($sort_order != 'asc') $sort_order = 'desc';
		$sort_link = $sort_order == 'asc' ? 'desc' : 'asc';		// Effectively toggle setting for headings
		$amount = 10;//TODO - pref

		$e107 = &e107::getInstance();

		if (isset($_POST['searchquery']))
		{
		$query = "news_title REGEXP('".$_POST['searchquery']."') OR news_body REGEXP('".$_POST['searchquery']."') OR news_extended REGEXP('".$_POST['searchquery']."') ORDER BY news_datestamp DESC";
		}
		else
		{
		$query = "ORDER BY ".($this->getSubAction() ? $this->getSubAction() : "news_datestamp")." ".strtoupper($sort_order)."  LIMIT ".$this->getFrom().", {$amount}";
		}

		if ($e107->sql->db_Select('news', '*', $query, ($_POST['searchquery'] ? 0 : "nowhere")))
		{
			$newsarray = $e107->sql->db_getList();
			$text = "
				<form action='".e_SELF."' id='newsform' method='post'>
					<fieldset id='core-newspost-list'>
						<legend class='e-hideme'>".NWSLAN_4."</legend>
						<table cellpadding='0' cellspacing='0' class='adminlist'>
							<colgroup span='4'>
								<col style='width:  5%'></col>
								<col style='width: 55%'></col>
								<col style='width: 15%'></col>
								<col style='width: 15%'></col>
							</colgroup>
							<thead>
								<tr>
									<th class='center'><a href='".e_SELF."?main.news_id.{$sort_link}.".$this->getFrom()."'>".LAN_NEWS_45."</a></th>
									<th><a href='".e_SELF."?main.news_title.{$sort_link}.".$this->getFrom()."'>".NWSLAN_40."</a></th>
									<th class='center'>".LAN_NEWS_49."</th>
									<th class='center last'>".LAN_OPTIONS."</th>
								</tr>
							</thead>
							<tbody>
			";
			$ren_type = array("default","title","other-news","other-news 2");
			foreach($newsarray as $row)
			{

				// Note: To fix the alignment bug. Put both buttons inside the Form.
				// But make EDIT a 'button' and DELETE 'submit'
				$text .= "
								<tr>
									<td class='center'>{$row['news_id']}</td>
									<td><a href='".$e107->url->getUrl('core:news', 'main', "action=item&value1={$row['news_id']}&value2={$row['news_category']}")."'>".($row['news_title'] ? $e107->tp->toHTML($row['news_title'], false,"TITLE") : "[".NWSLAN_42."]")."</a></td>
									<td class='center'>
				";
				$text .= $ren_type[$row['news_render_type']];
				if($row['news_sticky'])
				{
					$sicon = (file_exists(THEME_ABS."images/sticky.png") ? THEME_ABS."images/sticky.png" : e_IMAGE_ABS."generic/sticky.png");
					$text .= " <img src='".$sicon."' alt='' />";
				}
				//TODO - remove onclick events
				$text .= "
									</td>
									<td class='center'>
										<a class='action' href='".e_SELF."?create.edit.{$row['news_id']}' tabindex='".$frm->getNext()."'>".ADMIN_EDIT_ICON."</a>
										".$frm->submit_image("delete[main_{$row['news_id']}]", LAN_DELETE, 'delete', NWSLAN_39." [ID: {$row['news_id']}]")."
									</td>
								</tr>
				";
			}

			$text .= "
							</tbody>
						</table>
					</fieldset>
				</form>
			";
		}
		else
		{
			$text .= "<div style='text-align:center'>".NWSLAN_43."</div>";
		}

		$newsposts = $e107->sql->db_Count('news');

		if (!varset($_POST['searchquery']))
		{
			$parms = $newsposts.",".$amount.",".$this->getFrom().",".e_SELF."?".$this->getAction().'.'.$this->getSubAction().'.'.$sort_order."[FROM]";
			$nextprev = $e107->tp->parseTemplate("{NEXTPREV={$parms}}");
			if ($nextprev) $text .= "<div class='nextprev-bar'>".$nextprev."</div>";

		}


		$text .= "
			<form method='post' action='".e_SELF."'>
				<div class='buttons-bar center'>
					".$frm->text('searchquery', '', 50).$frm->admin_button('searchsubmit', NWSLAN_63, 'search')."
				</div>
			</form>
		";
		$emessage = &eMessage::getInstance();
		$e107->ns->tablerender(NWSLAN_4, $emessage->render().$text);
	}




	function create_item($sub_action, $id)
	{
		global $pref;
		// ##### Display creation form
		require_once(e_HANDLER."form_handler.php");
		$frm = new e_form(true); //enable inner tabindex counter

		$sub_action = $this->getSubAction();
		$id = $this->getId();

		$e107 = &e107::getInstance();

		if ($sub_action == "sn" && !varset($_POST['preview']))
		{
			if ($sql->db_Select("submitnews", "*", "submitnews_id={$id}", TRUE))
			{
				//list($id, $submitnews_name, $submitnews_email, $_POST['news_title'], $submitnews_category, $_POST['data'], $submitnews_datestamp, $submitnews_ip, $submitnews_auth, $submitnews_file) = $sql->db_Fetch();
				$row = $e107->sql->db_Fetch();
				$_POST['news_title'] = $row['submitnews_title'];
				$_POST['data'] = $row['submitnews_item'];
				$_POST['cat_id'] = $row['submitnews_category'];

				if (e_WYSIWYG)
				{
				  if (substr($_POST['data'],-7,7) == '[/html]') $_POST['data'] = substr($_POST['data'],0,-7);
				  if (substr($_POST['data'],0,6) == '[html]') $_POST['data'] = substr($_POST['data'],6);
					$_POST['data'] .= "<br /><b>".NWSLAN_49." {$row['submitnews_name']}</b>";
					$_POST['data'] .= ($row['submitnews_file'])? "<br /><br /><img src='{e_IMAGE}newspost_images/{$row['submitnews_file']}' class='f-right' />": '';
				}
				else
				{
					$_POST['data'] .= "\n[[b]".NWSLAN_49." {$row['submitnews_name']}[/b]]";
					$_POST['data'] .= ($row['submitnews_file'])?"\n\n[img]{e_IMAGE}newspost_images/{$row['submitnews_file']}[/img]": "";
				}

			}
		}

		if ($sub_action == "upload" && !varset($_POST['preview']))
		{
			if ($e107->sql->db_Select('upload', '*', "upload_id={$id}")) {
				$row = $sql->db_Fetch();
				$post_author_id = substr($row['upload_poster'], 0, strpos($row['upload_poster'], "."));
				$post_author_name = substr($row['upload_poster'], (strpos($row['upload_poster'], ".")+1));
				$match = array();
				//XXX DB UPLOADS STILL SUPPORTED?
				$upload_file = "pub_" . (preg_match('#Binary\s(.*?)\/#', $row['upload_file'], $match) ? $match[1] : $row['upload_file']);
				$_POST['news_title'] = LAN_UPLOAD.": ".$row['upload_name'];
				$_POST['data'] = $row['upload_description']."\n[b]".NWSLAN_49." <a href='user.php?id.".$e107->url->getUrl('core:user', 'main', 'id='.$post_author_id)."'>".$post_author_name."</a>[/b]\n\n[file=request.php?".$upload_file."]{$row['upload_name']}[/file]\n";
			}
		}

		$text = "
			<form method='post' action='".e_SELF."?".e_QUERY."' id='dataform' ".(FILE_UPLOADS ? "enctype='multipart/form-data'" : "")." >
				<fieldset id='core-newspost-edit'>
					<legend>".LAN_NEWS_52."</legend>
					<table cellpadding='0' cellspacing='0' class='adminedit'>
						<colgroup span='2'>
							<col class='col-label' />
							<col class='col-control' />
						</colgroup>
						<tbody>
							<tr>
								<td class='label'>".NWSLAN_6.": </td>
								<td class='control'>
		";

		if (!$e107->sql->db_Select("news_category"))
		{
			$text .= NWSLAN_10;
		}
		else
		{
			$text .= "
									".$frm->select_open('cat_id')."
			";

			while ($row = $e107->sql->db_Fetch())
			{
				$text .= $frm->option($e107->tp->toHTML($row['category_name'], FALSE, "LINKTEXT"), $row['category_id'], varset($_POST['cat_id']) == $row['category_id']);
			}
			$text .= "
									</select>
			";
		}
		$text .= "
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_12.":</td>
								<td class='control'>
									".$frm->text('news_title', $e107->tp->post_toForm($_POST['news_title']))."
								</td>
							</tr>

							<tr>
								<td class='label'>".LAN_NEWS_27.":</td>
								<td class='control'>
									".$frm->text('news_summary', $e107->tp->post_toForm($_POST['news_summary']), 250)."
								</td>
							</tr>
		";


		// -------- News Author ---------------------
        $text .="
							<tr>
								<td class='label'>
									".LAN_NEWS_50.":
								</td>
								<td class='control'>
		";

		if(!getperms('0') && !check_class($pref['news_editauthor']))
		{
			$auth = ($_POST['news_author']) ? intval($_POST['news_author']) : USERID;
			$e107->sql->db_Select("user", "user_name", "user_id={$auth} LIMIT 1");
           	$row = $e107->sql->db_Fetch(MYSQL_ASSOC);
			$text .= "<input type='hidden' name='news_author' value='".$auth.chr(35).$row['user_name']."' />";
			$text .= "<a href='".$e107->url->getUrl('core:user', 'main', 'id='.$_POST['news_author'])."'>".$row['user_name']."</a>";
		}
        else // allow master admin to
		{
			$text .= $frm->select_open('news_author');
			$qry = "SELECT user_id,user_name FROM #user WHERE user_perms = '0' OR FIND_IN_SET('H',user_perms) ";
			if($pref['subnews_class'] && $pref['subnews_class']!= e_UC_GUEST && $pref['subnews_class']!= e_UC_NOBODY)
			{
				if($pref['subnews_class']== e_UC_MEMBER)
				{
					$qry .= " OR user_ban != 1";
				}
				elseif($pref['subnews_class']== e_UC_ADMIN)
				{
	            	$qry .= " OR user_admin = 1";
				}
				else
				{
	            	$qry .= " OR FIND_IN_SET(".intval($pref['subnews_class']).", user_class) ";
				}
			}

	        $e107->sql->db_Select_gen($qry);
	        while($row = $e107->sql->db_Fetch())
	        {
	        	if($_POST['news_author'])
				{
		        	$sel = ($_POST['news_author'] == $row['user_id']);
		        }
				else
				{
		        	$sel = (USERID == $row['user_id']);
				}
				$text .= $frm->option($row['user_name'], $row['user_id'].chr(35).$row['user_name'], $sel);
			}

			$text .= "</select>
			";
		}

		$text .= "
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_13.":<br /></td>
								<td class='control'>";

		$val = (strstr($e107->tp->post_toForm($_POST['data']), "[img]http") ? $_POST['data'] : str_replace("[img]../", "[img]", $e107->tp->post_toForm($_POST['data'])));
        $text .= $frm->bbarea('data', $val, 'news', 'helpb');

		// Extended news form textarea
		// Fixes Firefox issue with hidden wysiwyg textarea.
		// XXX - WYSIWYG is already plugin, this should go
		if(defsettrue('e_WYSIWYG')) $ff_expand = "tinyMCE.execCommand('mceResetDesignMode')";
		$val = (strstr($e107->tp->post_toForm($_POST['news_extended']), "[img]http") ? $e107->tp->post_toForm($_POST['news_extended']) : str_replace("[img]../", "[img]", $e107->tp->post_toForm($_POST['news_extended'])));
		$text .= "
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_14.":</td>
								<td class='control'>
									<a href='#news_extended' class='e-expandit' onclick=\"$ff_expand\">".NWSLAN_83."</a>
									<div class='e-hideme'>
										".$frm->bbarea('news_extended', $val, 'extended', 'helpc')."
									</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_66.":</td>
								<td class='control'>
									<a class='e-pointer' onclick='expandit(this);'>".NWSLAN_69."</a>
									<div class='e-hideme'>
		";

		if (!FILE_UPLOADS)
		{
			$text .= "<b>".LAN_UPLOAD_SERVEROFF."</b>";
		}
		else
		{
			if (!is_writable(e_FILE."downloads"))
			{
				$text .= LAN_UPLOAD_777."<b>".str_replace("../","",e_FILE."downloads/")."</b><br /><br />";
			}
			if (!is_writable(e_IMAGE."newspost_images"))
			{
				$text .= LAN_UPLOAD_777."<b>".str_replace("../","",e_IMAGE."newspost_images/")."</b><br /><br />";
			}

			$up_name = array(LAN_NEWS_24, NWSLAN_67, LAN_NEWS_22, NWSLAN_68);
			$up_value = array("resize", "image", "thumb", "file");

			$text .= "
										<div id='up_container' >
											<span id='upline' class='nowrap'>
												".$frm->file('file_userfile[]')."
												".$frm->select_open('uploadtype[]')."
			";
			for ($i=0; $i<count($up_value); $i++)
			{
				$text .= $frm->option($up_name[$i], $up_value[$i], varset($_POST['uploadtype']) == $up_value[$i]);
			};
			//FIXME - upload shortcode, flexible enough to be used everywhere
			$text .= "
												</select>
											</span>
										</div>
										<table style='width:100%'>
											<tr>
												<td>".$frm->admin_button('dupfield', LAN_NEWS_26, 'action', '', array('other' => 'onclick="duplicateHTML(\'upline\',\'up_container\');"'))."</td>
												<td><span class='smalltext'>".LAN_NEWS_25."</span>&nbsp;>".$frm->text('resize_value', ($_POST['resize_value'] ? $_POST['resize_value'] : '100'), 4, 'size=3&class=tbox')."&nbsp;px</td>
												<td>".$frm->admin_button('submitupload', NWSLAN_66, 'upload')."</td>
											</tr>
										</table>
			";

		}
		$text .= "
									</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_67.":</td>
								<td class='control'>
									<a class='e-pointer' onclick='expandit(this);'>".LAN_NEWS_23."</a>
									<div class='e-hideme'>
		";

		$parms = "name=news_thumbnail";
		$parms .= "&path=".e_IMAGE."newspost_images/";
		$parms .= "&default=".$_POST['news_thumbnail'];
		$parms .= "&width=100px";
		$parms .= "&height=100px";
		$parms .= "&multiple=TRUE";
		$parms .= "&label=-- ".LAN_NEWS_48." --";
		$parms .= "&click_target=data";
		$parms .= "&click_prefix=[img][[e_IMAGE]]newspost_images/";
		$parms .= "&click_postfix=[/img]";


		$text .= $e107->tp->parseTemplate("{IMAGESELECTOR={$parms}}");

		$text .= "
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</fieldset>
		";

		//Begin Options block
		$text .= "
				<fieldset id='core-newspost-edit-options'>
					<legend>".LAN_OPTIONS."</legend>
					<table cellpadding='0' cellspacing='0' class='adminedit'>
						<colgroup span='2'>
							<col class='col-label' />
							<col class='col-control' />
						</colgroup>
						<tbody>
							<tr>
								<td class='label'>".NWSLAN_15.":</td>
								<td class='control'>
									<a class='e-pointer' onclick='expandit(this);'>".NWSLAN_18."</a>
									<div class='e-hideme'>
										". ($_POST['news_allow_comments'] ? "<input name='news_allow_comments' type='radio' value='0' />".LAN_ENABLED."&nbsp;&nbsp;<input name='news_allow_comments' type='radio' value='1' checked='checked' />".LAN_DISABLED : "<input name='news_allow_comments' type='radio' value='0' checked='checked' />".LAN_ENABLED."&nbsp;&nbsp;<input name='news_allow_comments' type='radio' value='1' />".LAN_DISABLED)."
									</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_73.":</td>
								<td class='control'>
									<a class='e-pointer' onclick='expandit(this);'>".NWSLAN_74."</a>
									<div class='e-hideme'>

		";
		$ren_type = array(NWSLAN_75,NWSLAN_76,NWSLAN_77,NWSLAN_77." 2");
		$r_array = array();
		foreach($ren_type as $key=>$value) {
			$r_array[$key] = $value;
			/*
			$checked = ($_POST['news_rendertype'] == $key) ? "checked='checked'" : "";
			$text .= "<input name='news_rendertype' type='radio' value='$key' $checked />";
			$text .= $value."<br />";
			*/
		}


		$text .= "
										".$frm->radio_multi('news_rendertype', $r_array, $_POST['news_rendertype'], true)."
									</div>
									</td>
								</tr>
								<tr>
									<td class='label'>".NWSLAN_19.":</td>
									<td class='control'>
									<a class='e-pointer' onclick='expandit(this);'>".NWSLAN_72."</a>
										<div class='e-hideme'>
											<div class='field-spacer'>".NWSLAN_21.":</div>
											<div class='field-spacer'>
		";

		$_startdate = ($_POST['news_start'] > 0) ? date("d/m/Y", $_POST['news_start']) : "";

		$cal_options['showsTime'] = false;
		$cal_options['showOthers'] = false;
		$cal_options['weekNumbers'] = false;
		$cal_options['ifFormat'] = "%d/%m/%Y";
		$cal_attrib['class'] = "tbox";
		$cal_attrib['size'] = "10";
		$cal_attrib['name'] = "news_start";
		$cal_attrib['value'] = $_startdate;
		$text .= $this->_cal->make_input_field($cal_options, $cal_attrib);

		$text .= " - ";

		$_enddate = ($_POST['news_end'] > 0) ? date("d/m/Y", $_POST['news_end']) : "";

		unset($cal_options);
		unset($cal_attrib);
		$cal_options['showsTime'] = false;
		$cal_options['showOthers'] = false;
		$cal_options['weekNumbers'] = false;
		$cal_options['ifFormat'] = "%d/%m/%Y";
		$cal_attrib['class'] = "tbox";
		$cal_attrib['size'] = "10";
		$cal_attrib['name'] = "news_end";
		$cal_attrib['value'] = $_enddate;
		$text .= $this->_cal->make_input_field($cal_options, $cal_attrib);

		$text .= "
											</div>
										</div>
									</td>
								</tr>
								<tr>
									<td class='label'>".LAN_NEWS_32.":</td>
									<td class='control'>
										<a class='e-pointer' onclick='expandit(this);'>".LAN_NEWS_33."</a>
										<div class='e-hideme'>
											<div class='field-spacer'>
		";

		$_update_datestamp = ($_POST['news_datestamp'] > 0 && !strpos($_POST['news_datestamp'],"/")) ? date("d/m/Y H:i:s", $_POST['news_datestamp']) : trim($_POST['news_datestamp']);
		unset($cal_options);
		unset($cal_attrib);
		$cal_options['showsTime'] = true;
		$cal_options['showOthers'] = true;
		$cal_options['weekNumbers'] = false;
		$cal_options['ifFormat'] = "%d/%m/%Y %H:%M:%S";
		$cal_options['timeFormat'] = "24";
		$cal_attrib['class'] = "tbox";
		$cal_attrib['name'] = "news_datestamp";
		$cal_attrib['value'] = $_update_datestamp;
		$text .= $this->_cal->make_input_field($cal_options, $cal_attrib);

		$text .= "
											</div>
											<div class='field-spacer'>
												".$frm->checkbox('update_datestamp', '1', $_POST['update_datestamp']).$frm->label(NWSLAN_105, 'update_datestamp', '1')."
											</div>
										</div>
									</td>
								</tr>
		";




        // --------------------- News Userclass ---------------------------

		$text .= "
								<tr>
									<td class='label'>".NWSLAN_22.":</td>
									<td class='control'>
										<a class='e-pointer' onclick='expandit(this);'>".NWSLAN_84."</a>
										<div class='e-hideme'>
											".r_userclass_check("news_userclass", $_POST['news_class'], "nobody,public,guest,member,admin,classes,language")."
										</div>
									</td>
								</tr>
								<tr>
									<td class='label'>".LAN_NEWS_28.":</td>
									<td class='control'>
										<a class='e-pointer' onclick='expandit(this);'>".LAN_NEWS_29."</a>
										<div class='e-hideme'>
											".$frm->checkbox('news_sticky', '1', $_POST['news_sticky']).$frm->label(LAN_NEWS_30, 'news_sticky', '1')."

										</div>
									</td>
								</tr>
		";

		if($pref['trackbackEnabled']){
			$text .= "
								<tr>
									<td class='label'>".LAN_NEWS_34.":</td>
									<td class='control'>
										<a class='e-pointer' onclick='expandit(this);'>".LAN_NEWS_35."</a>
										<div class='e-hideme'>
											<div class='field-spacer'>
												<span class='smalltext'>".LAN_NEWS_37."</span>
											</div>
											<div class='field-spacer'>
												<textarea class='tbox textarea' name='trackback_urls' style='width:95%' cols='80' rows='5'>".$_POST['trackback_urls']."</textarea>
											</div>
										</div>
									</td>
								</tr>
			";
		}
		//triggerHook
		$data = array('method'=>'form', 'table'=>'news', 'id'=>$id, 'plugin'=>'news', 'function'=>'create_item');
		$hooks = $e107->e_event->triggerHook($data);
		if(!empty($hooks))
		{
			$text .= "
								<tr>
									<td colspan='2' >".LAN_HOOKS." </td>
								</tr>
			";
			foreach($hooks as $hook)
			{
				if(!empty($hook))
				{
					$text .= "
								<tr>
									<td class='label'>".$hook['caption']."</td>
									<td class='control'>".$hook['text']."</td>
								</tr>
					";
				}
			}
		}

		$text .= "
						</tbody>
					</table>
				</fieldset>
				<div class='buttons-bar center'>
					".$frm->admin_button('preview', isset($_POST['preview']) ? NWSLAN_24 : NWSLAN_27 , 'submit')."
					".$frm->admin_button('submit_news', ($id && $sub_action != "sn" && $sub_action != "upload") ? NWSLAN_25 : NWSLAN_26 , 'update')."
					<input type='hidden' name='news_id' value='{$id}' />
				</div>
			</form>


		";
			$emessage = &eMessage::getInstance();
		$e107->ns->tablerender(NWSLAN_29, $emessage->render().$text);
	}


	function preview_item($id)
	{
		// ##### Display news preview ---------------------------------------------------------------------------------------------------------
		global $tp, $sql, $ix, $IMAGES_DIRECTORY;

		$_POST['news_id'] = $id;

		if($_POST['news_start'])
		{
			$tmp = explode("/", $_POST['news_start']);
			$_POST['news_start'] = mktime(0, 0, 0, $tmp[1], $tmp[0], $tmp[2]);
		}
		else
		{
			$_POST['news_start'] = 0;
		}

		if($_POST['news_end'])
		{
			$tmp = explode("/", $_POST['news_end']);
			$_POST['news_end'] = mktime(0, 0, 0, $tmp[1], $tmp[0], $tmp[2]);
		}
		else
		{
			$_POST['news_end'] = 0;
		}

		if(preg_match("#(.*?)/(.*?)/(.*?) (.*?):(.*?):(.*?)$#", $_POST['news_datestamp'], $matches))
		{
			$_POST['news_datestamp'] = mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[1], $matches[3]);
		}
		else
		{
			$_POST['news_datestamp'] = time();
		}

		if($_POST['update_datestamp'])
		{
			$_POST['news_datestamp'] = time();
		}

		$sql->db_Select("news_category", "*", "category_id='".$_POST['cat_id']."' ");
		list($_POST['category_id'], $_POST['category_name'], $_POST['category_icon']) = $sql->db_Fetch();
	  //	$_POST['user_id'] = USERID;
	   //	$_POST['user_name'] = USERNAME."blabla";
	   	list($_POST['user_id'],$_POST['user_name']) = explode(chr(35),$_POST['news_author']);
		$_POST['news_author'] = $_POST['user_id'];
		$_POST['comment_total'] = $comment_total;
		$_PR = $_POST;

		$_PR['news_body'] = $tp->post_toHTML($_PR['data'],FALSE);
		$_PR['news_title'] = $tp->post_toHTML($_PR['news_title'],FALSE,"emotes_off, no_make_clickable");
		$_PR['news_summary'] = $tp->post_toHTML($_PR['news_summary']);
		$_PR['news_extended'] = $tp->post_toHTML($_PR['news_extended']);
		$_PR['news_file'] = $_POST['news_file'];
		$_PR['news_image'] = $_POST['news_image'];

		$ix -> render_newsitem($_PR);
		echo $tp -> parseTemplate('{NEWSINFO}', FALSE, $news_shortcodes);
	}

	function show_message($message, $type = '')
	{
		// ##### Display comfort ---------------------------------------------------------------------------------------------------------
		//global $ns;
		//$ns->tablerender("", "<div style='text-align:center'><b>".$message."</b></div>");
		$emessage = &eMessage::getInstance();
		$emessage->add($message, $type OR E_MESSAGE_INFO);
	}

	function show_categories($sub_action, $id)
	{
		global $sql, $rs, $ns, $tp, $frm;
		$handle = opendir(e_IMAGE."icons");
		while ($file = readdir($handle)) {
			if ($file != "." && $file != ".." && $file != "/" && $file != "null.txt" && $file != "CVS") {
				$iconlist[] = $file;
			}
		}
		closedir($handle);

		if ($sub_action == "edit") {
			if ($sql->db_Select("news_category", "*", "category_id='$id' ")) {
				$row = $sql->db_Fetch();
				extract($row);
			}
		}

		$text = "
			<form method='post' action='".e_SELF."?cat' id='dataform'>
				<fieldset id='core-newspost-cat-edit'>
					<legend>".NWSLAN_56."</legend>
					<table cellpadding='0' cellspacing='0' class='adminform'>
						<colgroup span='2'>
							<col class='col-label' />
							<col class='col-control' />
						</colgroup>
						<tbody>
							<tr>
								<td class='label'>".NWSLAN_52."</td>
								<td class='control'>
									".$frm->text('category_name', $category_name, 200)."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_53."</td>
								<td class='control'>
									<div class='field-spacer'>
										".$frm->text('category_button', $category_icon, 100)."
									</div>
									<div class='field-spacer'>
										".$frm->admin_button('view_images', NWSLAN_54, 'action', NWSLAN_54, array('other'=>"onclick='e107Helper.toggle(\"caticn\")'"))."
									</div>
									<div id='caticn' class='e-hideme'>
		";

		while (list($key, $icon) = each($iconlist)) {
			$text .= "
										<a href=\"javascript:insertext('$icon','category_button','caticn')\"><img src='".e_IMAGE."icons/".$icon."' alt='' /></a>
			";
		}

		$text .= "
									</div>
								</td>
							</tr>
						</tbody>
					</table>
					<div class='buttons-bar center'>
		";
		if ($id) {
			$text .= "
				".$frm->admin_button('update_category', NWSLAN_55, 'update')."
				".$frm->admin_button('category_clear', NWSLAN_79)."
				".$frm->hidden("category_id", $id)."
			";
			} else {
			$text .= "
				".$frm->admin_button('create_category', NWSLAN_56, 'create')."
			";
		}

		$text .= "
					</div>
				</fieldset>
			</form>
		";

		//$ns->tablerender(NWSLAN_56, $text);

		unset($category_name, $category_icon);
		//XXX LAN - Icon
		$text .= "
			<form action='".e_SELF."?cat' id='newscatform' method='post'>
				<fieldset id='core-newspost-cat-list'>
					<legend>".NWSLAN_51."</legend>
					<table cellpadding='0' cellspacing='0' class='adminlist'>
						<colgroup span='4'>
							<col style='width: 	5%'></col>
							<col style='width:  10%'></col>
							<col style='width:  70%'></col>
							<col style='width:  15%'></col>
						</colgroup>
						<thead>
							<tr>
								<th class='center'>".LAN_NEWS_45."</th>
								<th class='center'>Icon</th>
								<th>".NWSLAN_6."</th>
								<th class='center last'>".LAN_OPTIONS."</th>
							</tr>
						</thead>
						<tbody>
		";
		if ($category_total = $sql->db_Select("news_category")) {
			while ($row = $sql->db_Fetch()) {
				extract($row);

				if ($category_icon) {
					$icon = (strstr($category_icon, "images/") ? THEME."$category_icon" : e_IMAGE."icons/$category_icon");
				}

				$text .= "
							<tr>
								<td class='center'>{$category_id}</td>
								<td class='center'><img src='$icon' alt='' style='vertical-align:middle' /></td>
								<td>$category_name</td>
								<td class='center'>
									<a href='".e_SELF."?cat.edit.{$category_id}'>".ADMIN_EDIT_ICON."</a>
									<input type='image' title='".LAN_DELETE."' name='delete[category_{$category_id}]' src='".ADMIN_DELETE_ICON_PATH."' onclick=\"return jsconfirm('".$tp->toJS(NWSLAN_37." [ID: $category_id ]")."') \"/>
								</td>
							</tr>
				";
			}
			$text .= "
						</tbody>
					</table>
			";
			} else {
			$text .= "<div class='center'>".NWSLAN_10."</div>";
		}

		$text .= "

			</fieldset>
		</form>
		";

		$ns->tablerender(NWSLAN_46, $text);
	}


	function show_news_prefs()
	{
		global $sql, $rs, $ns, $pref, $frm;

		$text = "
			<form method='post' action='".e_SELF."?pref' id='dataform'>
				<fieldset id='core-newspost-settings'>
					<legend class='e-hideme'>".NWSLAN_90."</legend>
					<table cellpadding='0' cellspacing='0' class='adminform'>
						<colgroup span='2'>
							<col class='col-label' />
							<col class='col-control' />
						</colgroup>
						<tbody>
							<tr>
								<td class='label'>".NWSLAN_86."</td>
								<td class='control'>
									".$frm->checkbox('news_cats', '1', ($pref['news_cats'] == 1))."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_87."</td>
								<td class='control'>
									<select class='tbox' name='nbr_cols'>
										<option value='1' ".($pref['nbr_cols'] == 1 ? "selected='selected'>" : "").">1</option>
										<option value='2' ".($pref['nbr_cols'] == 2 ? "selected='selected'>" : "").">2</option>
										<option value='3' ".($pref['nbr_cols'] == 3 ? "selected='selected'>" : "").">3</option>
										<option value='4' ".($pref['nbr_cols'] == 4 ? "selected='selected'>" : "").">4</option>
										<option value='5' ".($pref['nbr_cols'] == 5 ? "selected='selected'>" : "").">5</option>
										<option value='6' ".($pref['nbr_cols'] == 6 ? "selected='selected'>" : "").">6</option>
									</select>
								</td>
							</tr>
							<tr>
							<td class='label'>".NWSLAN_88."</td>
							<td class='control'>
								".$frm->text('newsposts', $pref['newsposts'])."
							</td>
							</tr>
		";


		// ##### ADDED FOR NEWS ARCHIVE --------------------------------------------------------------------
		// the possible archive values are from "0" to "< $pref['newsposts']"
		// this should really be made as an onchange event on the selectbox for $pref['newsposts'] ...
		$text .= "
							<tr>
								<td class='label'>".NWSLAN_115."</td>
								<td class='control'>
									<select class='tbox' name='newsposts_archive'>
		";
		for($i = 0; $i < $pref['newsposts']; $i++) {
			$text .= ($i == $pref['newsposts_archive'] ? "<option value='".$i."' selected='selected'>".$i."</option>" : " <option value='".$i."'>".$i."</option>");
		}
		$text .= "
									</select>
									<div class='field-help'>".NWSLAN_116."</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_117."</td>
								<td class='control'>
									".$frm->text('newsposts_archive_title', $pref['newsposts_archive_title'])."
								</td>
							</tr>
		";
		// ##### END --------------------------------------------------------------------------------------


		require_once(e_HANDLER."userclass_class.php");

		$text .= "
							<tr>
								<td class='label'>".LAN_NEWS_51."</td>
								<td class='control'>
									".r_userclass("news_editauthor", $pref['news_editauthor'],"off","nobody,mainadmin,admin,classes")."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_106."</td>
								<td class='control'>
									".r_userclass("subnews_class", $pref['subnews_class'],"off","nobody,public,guest,member,admin,classes")."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_107."</td>
								<td class='control'>
									".$frm->checkbox('subnews_htmlarea', '1', $pref['subnews_htmlarea'])."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_100."</td>
								<td class='control'>
									".$frm->checkbox('subnews_attach', '1', $pref['subnews_attach'])."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_101."</td>
								<td class='control'>
									<input class='tbox' type='text' style='width:50px' name='subnews_resize' value='".$pref['subnews_resize']."' />
									".NWSLAN_102."
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_111."</td>
								<td class='control'>
									".$frm->checkbox('news_newdateheader', '1', ($pref['news_newdateheader'] == 1))."
									<div class='field-help'>".NWSLAN_112."</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_113."</td>
								<td class='control'>
									".$frm->checkbox('news_unstemplate', '1', ($pref['news_unstemplate'] == 1))."
									<div class='field-help'>".NWSLAN_114."</div>
								</td>
							</tr>
							<tr>
								<td class='label'>".NWSLAN_120."</td>
								<td class='control'>
									<textarea name='news_subheader' style='width:95%;' rows='6' cols='80' onselect='storeCaret(this);' onclick='storeCaret(this);' onkeyup='storeCaret(this);' class='tbox'>".stripcslashes($pref['news_subheader'])." </textarea><br />" . display_help('helpb', 2) . "
								</td>
							</tr>
						</tbody>
					</table>
					<div class='buttons-bar center'>
						".$frm->admin_button('save_prefs', NWSLAN_89, 'update')."
					</div>
				</fieldset>
			</form>
		";

		$ns->tablerender(NWSLAN_90, $text);
	}



	//XXX Lan - many many lans
	function submitted_news($sub_action, $id)
	{
		global $rs, $ns, $tp, $frm, $e107;
		$sql2 = new db;
		if ($category_total = $sql2->db_Select("submitnews", "*", "submitnews_id !='' ORDER BY submitnews_id DESC"))
		{
			$text .= "
			<form action='".e_SELF."?sn' method='post'>
				<fieldset id='core-newspost-sn-list'>
					<legend class='e-hideme'>".NWSLAN_47."</legend>
					<table cellpadding='0' cellspacing='0' class='adminlist'>
						<colgroup span='6'>
							<col style='width: 5%'></col>
							<col style='width: 75%'></col>
							<col style='width: 20%'></col>
						</colgroup>
						<thead>
							<tr>
								<th class='center'>ID</th>
								<th>".NWSLAN_57."</th>
								<th class='center last'>".LAN_OPTIONS."</th>
							</tr>
						</thead>
						<tbody>
			";
			while ($row = $sql2->db_Fetch())
			{
				extract($row);
				$buttext = ($submitnews_auth == 0)? NWSLAN_58 :	NWSLAN_103;

				if (substr($submitnews_item,-7,7) == '[/html]') $submitnews_item = substr($submitnews_item,0,-7);
				if (substr($submitnews_item,0,6) == '[html]') $submitnews_item = substr($submitnews_item,6);

				$text .= "
					<tr>
						<td class='center'>{$submitnews_id}</td>
						<td>
							<strong>".$tp->toHTML($submitnews_title,FALSE,"emotes_off, no_make_clickable")."</strong><br/>".$tp->toHTML($submitnews_item)."
						</td>
						<td>
							<div class='field-spacer'><strong>Posted:</strong> ".(($submitnews_auth == 0) ? "No" : "Yes")."</div>
							<div class='field-spacer'><strong>Date:</strong> ".date("D dS M y, g:ia", $submitnews_datestamp)."</div>
							<div class='field-spacer'><strong>User:</strong> ".$submitnews_name."</div>
							<div class='field-spacer'><strong>Email:</strong> ".$submitnews_email."</div>
							<div class='field-spacer'><strong>IP:</strong> ".$e107->ipDecode($submitnews_ip)."</div>
							<br/>
							<div class='field-spacer center'>
								".$frm->admin_button("category_edit_{$submitnews_id}", $buttext, 'action', '', array('id'=>false, 'other'=>"onclick=\"document.location='".e_SELF."?create.sn.$submitnews_id'\""))."
								".$frm->admin_button("delete[sn_{$submitnews_id}]", LAN_DELETE, 'delete', '', array('id'=>false, 'title'=>$tp->toJS(NWSLAN_38." [ID: {$submitnews_id} ]")))."
							</div>
						</td>
					</tr>
				";
			}
			$text .= "
						</tbody>
					</table>
				</fieldset>
			</form>
			";
		}
		else
		{
			$text .= "<div style='text-align:center'>".NWSLAN_59."</div>";
		}

		$ns->tablerender(NWSLAN_47, $text);
	}

	function show_options()
	{
		$e107 = &e107::getInstance();

		$var['main']['text'] = NWSLAN_44;
		$var['main']['link'] = e_SELF;

		$var['create']['text'] = NWSLAN_45;
		$var['create']['link'] = e_SELF."?create";

		$var['cat']['text'] = NWSLAN_46;
		$var['cat']['link'] = e_SELF."?cat";
		$var['cat']['perm'] = "7";

		$var['pref']['text'] = NWSLAN_90;
		$var['pref']['link'] = e_SELF."?pref";
		$var['pref']['perm'] = "N";
		if ($e107->sql->db_Select('submitnews', '*', "submitnews_auth=0")) {
			$var['sn']['text'] = NWSLAN_47;
			$var['sn']['link'] = e_SELF."?sn";
			$var['sn']['perm'] = "N";
		}

		e_admin_menu(NWSLAN_48, $this->getAction(), $var);
	}

}

function newspost_adminmenu()
{
	global $newspost;
	$newspost->show_options();
}


?>
