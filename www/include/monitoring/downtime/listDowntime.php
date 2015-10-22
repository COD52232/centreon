<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
	exit();
}

include_once _CENTREON_PATH_."www/class/centreonGMT.class.php";

include("./include/common/autoNumLimit.php");

if (isset($_POST["search_service"]))
  	$search_service = $_POST["search_service"];
else if (isset($_GET["search_service"]))
  	$search_service = $_GET["search_service"];
else
  	$search_service = NULL;

if (isset($_POST["search_host"]))
	$host_name = $_POST["search_host"];
else if (isset($_GET["search_host"]))
	$host_name = $_GET["search_host"];
else
	$host_name = NULL;

if (isset($_POST["search_output"]))
	$search_output = $_POST["search_output"];
else if (isset($_GET["search_output"]))
	$search_output = $_GET["search_output"];
else
	$search_output = NULL;

if (isset($_POST["view_all"]))
	$view_all = 1;
else if (isset($_GET["view_all"]) && !isset($_POST["SearchB"]))
	$view_all = 1;
else
	$view_all = 0;

if (isset($_POST["view_downtime_cycle"]))
	$view_downtime_cycle = 1;
else if (isset($_GET["view_downtime_cycle"]) && !isset($_POST["SearchB"]))
	$view_downtime_cycle = 1;
else
	$view_downtime_cycle = 0;

if (isset($_POST["search_author"]))
	$search_author = $_POST["search_author"];
else if (isset($_GET["search_author"]) && !isset($_POST["SearchB"]))
	$search_author = $_GET["search_author"];
else
	$search_author = NULL;

/*
 * Init GMT class
 */
$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession(session_id(), $pearDB);

include_once("./class/centreonDB.class.php");

/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl, "template/");

/*
 * Pear library
 */
require_once "HTML/QuickForm.php";
require_once 'HTML/QuickForm/advmultiselect.php';
require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

$form = new HTML_QuickForm('select_form', 'GET', "?p=".$p);

$tab_downtime_svc = array();

/*
 * Service Downtimes
 */
if ($view_all == 1) {
	$downtimeTable = "downtimehistory";
	if ($oreon->broker->getBroker() == "ndo") {
	    $extrafields = ", UNIX_TIMESTAMP(dtm.actual_end_time) as actual_end_time, was_cancelled ";
	} else {
	    $extrafields = ", actual_end_time, cancelled as was_cancelled ";
	}
} else {
	$downtimeTable = "scheduleddowntime";
	$extrafields = "";
}

$request =  "SELECT SQL_CALC_FOUND_ROWS d.internal_id as internal_downtime_id,
        d.entry_time, duration, d.author as author_name, d.comment_data,
        d.fixed as is_fixed, d.start_time as scheduled_start_time, d.end_time as scheduled_end_time,
        d.started as was_started, h.name as host_name, s.description as service_description " . $extrafields .
        "FROM downtimes d, services s, hosts h " .
        "WHERE d.host_id = s.host_id AND
            d.service_id = s.service_id AND
            s.host_id = h.host_id ";
if (!$view_all) {
    $request .= " AND d.cancelled = 0 ";
}
if (!$is_admin) {
    $request .= " AND EXISTS(SELECT 1 FROM centreon_acl WHERE s.host_id = centreon_acl.host_id AND s.service_id = centreon_acl.service_id AND group_id IN (" . $oreon->user->access->getAccessGroupsString() . ")) ";
}
$request .= (isset($search_service) && $search_service != "" ? "AND s.description LIKE '%$search_service%' " : "") .
            (isset($host_name) && $host_name != "" ? "AND h.name LIKE '%$host_name%' " : "") .
            (isset($search_output) && $search_output != "" ? "AND d.comment_data LIKE '%$search_output%' " : "") .
            (isset($view_all) && $view_all == 0 ? "AND d.end_time > '".time()."' " : "") .
            (isset($view_downtime_cycle) && $view_downtime_cycle == 0 ? " AND d.comment_data NOT LIKE '%Downtime cycle%' " : "") .
            (isset($search_author) && $search_author != "" ? " AND d.author LIKE '%$search_author%'" : "") .
            "ORDER BY d.start_time DESC " .
            "LIMIT ".$num * $limit.", ".$limit;
$DBRESULT_NDO = $pearDBO->query($request);
$rows = $pearDBO->numberRows();
for ($i = 0; $data = $DBRESULT_NDO->fetchRow(); $i++) {
	$tab_downtime_svc[$i] = $data;
    $tab_downtime_svc[$i]['comment_data'] = htmlentities(trim($data['comment_data']));
	$tab_downtime_svc[$i]['host_name'] = htmlentities($data['host_name']);
	$tab_downtime_svc[$i]['service_description'] = htmlentities($data['service_description']);
	$tab_downtime_svc[$i]["scheduled_start_time"] = $centreonGMT->getDate("m/d/Y H:i" , $tab_downtime_svc[$i]["scheduled_start_time"])." ";
	$tab_downtime_svc[$i]["scheduled_end_time"] = $centreonGMT->getDate("m/d/Y H:i" , $tab_downtime_svc[$i]["scheduled_end_time"])." ";
	$tab_downtime_svc[$i]["host_name_link"] = urlencode($tab_downtime_svc[$i]["host_name"]);
}
unset($data);

/*
 * Number Rows
 */
include("./include/common/checkPagination.php");

$en = array("0" => _("No"), "1" => _("Yes"));
foreach ($tab_downtime_svc as $key => $value) {
	$tab_downtime_svc[$key]["is_fixed"] = $en[$tab_downtime_svc[$key]["is_fixed"]];
	$tab_downtime_svc[$key]["was_started"] = $en[$tab_downtime_svc[$key]["was_started"]];
	if ($view_all == 1) {
	    if (!isset($tab_downtime_svc[$key]["actual_end_time"]) || !$tab_downtime_svc[$key]["actual_end_time"]) {
	        if ($tab_downtime_svc[$key]["was_cancelled"] == 0) {
	            $tab_downtime_svc[$key]["actual_end_time"] = _("N/A");
	        } else {
	            $tab_downtime_svc[$key]["actual_end_time"] = _("Never Started");
	        }
	    } else {
	        $tab_downtime_svc[$key]["actual_end_time"] = $centreonGMT->getDate("m/d/Y H:i" , $tab_downtime_svc[$key]["actual_end_time"])." ";
	    }
	    $tab_downtime_svc[$key]["was_cancelled"] = $en[$tab_downtime_svc[$key]["was_cancelled"]];
	}
}
/*
 * Element we need when we reload the page
 */
$form->addElement('hidden', 'p');
$tab = array ("p" => $p);
$form->setDefaults($tab);

if ($oreon->user->access->checkAction("service_schedule_downtime")) {
	$tpl->assign('msgs', array ("addL"=>"?p=".$p."&o=as", "addT"=>_("Add a downtime"), "delConfirm"=>_("Do you confirm the deletion ?")));
}

$tpl->assign("p", $p);
$tpl->assign("o", $o);

$tpl->assign("tab_downtime_svc", $tab_downtime_svc);
$tpl->assign("nb_downtime_svc", count($tab_downtime_svc));

$tpl->assign("dtm_host_name", _("Host Name"));
$tpl->assign("dtm_service_descr", _("Services"));
$tpl->assign("dtm_start_time", _("Start Time"));
$tpl->assign("dtm_end_time", _("End Time"));
$tpl->assign("dtm_author", _("Author"));
$tpl->assign("dtm_comment", _("Comments"));
$tpl->assign("dtm_fixed", _("Fixed"));
$tpl->assign("dtm_duration", _("Duration"));
$tpl->assign("dtm_started", _("Started"));
$tpl->assign("dtm_service_downtime", _("Services Downtimes"));
$tpl->assign("dtm_service_cancelled", _("Cancelled"));
$tpl->assign("dtm_service_actual_end", _("Actual End"));

$tpl->assign("secondes", _("s"));

$tpl->assign("no_svc_dtm", _("No downtime scheduled for services"));
$tpl->assign("view_host_dtm", _("View downtimes of hosts"));
$tpl->assign("host_dtm_link", "./main.php?p=".$p."&o=vh");
$tpl->assign("cancel", _("Cancel"));
$tpl->assign("delete", _("Delete"));
$tpl->assign("limit", $limit);

$tpl->assign("Host", _("Host Name"));
$tpl->assign("Service", _("Service"));
$tpl->assign("Output", _("Output"));
$tpl->assign("user", _("Users"));
$tpl->assign('Hostgroup', _("Hostgroup"));
$tpl->assign('Search', _("Search"));
$tpl->assign("ViewAll", _("Display Finished Downtime"));
$tpl->assign("ViewDowntimeCycle", _("Display Downtime Cycle"));
$tpl->assign("Author", _("Author"));
$tpl->assign("search_output", $search_output);
$tpl->assign('search_host', $host_name);
$tpl->assign("search_service", $search_service);
$tpl->assign('view_all', $view_all);
$tpl->assign('view_downtime_cycle', $view_downtime_cycle);
$tpl->assign('search_author', $search_author);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display("listDowntime.ihtml");
?>
<script type='text/javascript'>
var msgArr = new Array();
msgArr['cs'] = '<?php echo addslashes(_("Do you confirm the cancellation ?")); ?>';
msgArr['ds'] = '<?php echo addslashes(_("Do you confirm the deletion ?")); ?>';

function doAction(slt, act) {
	if (confirm(msgArr[act])) {
            jQuery('input[name=o]').attr('value', act);
            document.form.submit();
	} else {
            slt.value = 0;
	}
}
</script>
