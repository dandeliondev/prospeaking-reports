<?php
require_once __DIR__ . '/../config/bootstrap.php';

$psl1 = connectToCluster('psl1', $clusters);

if ( isset( $_GET[ 'list' ] ) ) {
    $list = $_GET['list'];
    $delete = file_get_contents( "{$clusters["psl1"]["url"]}/vicidial/non_agent_api.php?source=adminTools&user=$apiUser&pass=$apiPass&function=update_list&list_id=$list&delete_leads=Y&delete_list=Y" );
    if (strpos($delete, "LIST HAS BEEN DELETED") !== false && strpos($delete, "LEADS IN LIST HAVE BEEN DELETED") !== false) {
        echo "<div align='center'>Successfully deleted list ID: $list</div>";
    } else {
        echo "<div align='center'>There was an error trying to delete this list. See J<br /><br />$delete</div>";
    }

} else {
    echo "<h4 align='center' style='color:red'>WARNING: ONLY DELETE LISTS WHEN THE DIALER IS NOT RUNNING.</h4><div align='center' style='margin-bottom:40px'>Deleting lists while the dialer is running will impact your dialing</div>

<div align='center'><select id='list'><option selected disabled></option>";

    $getLists = $psl1->query( "select list_id, list_name, campaign_id from vicidial_lists where list_id between 100001 and 299999 and list_id <> 200000 order by campaign_id, list_id" );
    $camp = "";
    foreach ( $getLists as $list ) {
        if ( $camp !== $list[ 'campaign_id' ] ) {
            echo "<option style='background-color:#CCC; color:#000' disabled>{$list['campaign_id']}</option>";
        }
        echo "<option value='{$list['list_id']}'>{$list['list_name']}</option>";
        $camp = $list[ 'campaign_id' ];
    }
    echo "</select>&nbsp;<button data-v='Lists/deleteLists?confirm=Are_you_sure_you_want_to_delete_this_list/_Doing_so_will_also_delete_all_the_leads_inside_this_list&list=' onclick='fetchURL(this.getAttribute(\"data-v\"));'>Go</button>";
}
