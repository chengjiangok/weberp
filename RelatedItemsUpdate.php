<?php

include('includes/session.php');

$Title = _('Update Related Items');

include('includes/header.php');
include('includes/SQL_CommonFunctions.inc');

//initialise no input errors assumed initially before we test
$InputError = 0;


if (isset($_GET['Item'])){
	$Item = trim(mb_strtoupper($_GET['Item']));
}elseif (isset($_POST['Item'])){
	$Item = trim(mb_strtoupper($_POST['Item']));
}
if (isset($_GET['Related'])){
	$Related = trim(mb_strtoupper($_GET['Related']));
}elseif (isset($_POST['Related'])){
	$Related = trim(mb_strtoupper($_POST['Related']));
}
echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/inventory.png" title="' . _('Search') . '" alt="" />' . $Title . '</p>';

echo '<a href="' . $RootPath . '/SelectProduct.php">' . _('Back to Items') . '</a><br />';


$result = DB_query("SELECT stockmaster.description
					FROM stockmaster
					WHERE stockmaster.stockid='".$Item."'");
$myrow = DB_fetch_row($result);

if (DB_num_rows($result)==0){
	prnMsg( _('The part code entered does not exist in the database') . ': ' . $Item . _('Only valid parts can have related items entered against them'),'error');
	$InputError=1;
}


if (!isset($Item)){
	echo '<p>';
	prnMsg (_('An item must first be selected before this page is called') . '. ' . _('The product selection page should call this page with a valid product code'),'error');
	include('includes/footer.php');
	exit;
}

$PartDescription = $myrow[0];

if (isset($_POST['submit'])) {

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible

	$result_related = DB_query("SELECT stockmaster.description,
							stockmaster.mbflag
					FROM stockmaster
					WHERE stockmaster.stockid='".$_POST['Related']."'");
	$myrow_related = DB_fetch_row($result_related);

	if (DB_num_rows($result_related)==0){
		prnMsg( _('The part code entered as related item does not exist in the database') . ': ' . $_POST['Related'] .  _('Only valid parts can be related items'),'error');
		$InputError=1;
	}

	$sql = "SELECT related
				FROM relateditems
			WHERE stockid='".$Item."'
				AND related = '" . $_POST['Related'] . "'";
	$result = DB_query($sql);
	$myrow = DB_fetch_row($result);

	if (DB_num_rows($result)!=0){
		prnMsg( _('This related item has already been entered.') , 'warn');
		$InputError =1;
	}

	if ($_POST['Related'] == $Item){
		prnMsg( _('An item can not be related to itself') , 'warn');
		$InputError =1;
	}

	if ($InputError !=1) {
		$sql = "INSERT INTO relateditems (stockid,
									related)
							VALUES ('" . $Item . "',
								'" . $_POST['Related'] . "')";
		$ErrMsg = _('The new related item could not be added');
		$result = DB_query($sql,$ErrMsg);

		prnMsg($_POST['Related'] . ' ' . _('is now related to') . ' ' . $Item,'success');

		/* It is safe to assume that, if A is related to B, B is related to A */
		$sql_reverse = "SELECT related
					FROM relateditems
				WHERE stockid='".$_POST['Related']."'
					AND related = '" . $Item . "'";
		$result_reverse = DB_query($sql_reverse);
		$myrow_reverse = DB_fetch_row($result_reverse);

		if (DB_num_rows($result_reverse)==0){
			$sql = "INSERT INTO relateditems (stockid,
										related)
								VALUES ('" . $_POST['Related'] . "',
									'" . $Item . "')";
			$ErrMsg = _('The new related item could not be added');
			$result = DB_query($sql,$ErrMsg);
			prnMsg($Item . ' ' . _('is now related to') . ' ' . $_POST['Related'],'success');
		}
	}

	unset($_POST['Related']);

} elseif (isset($_GET['delete'])) {
//the link to delete a selected record was clicked instead of the submit button

	/* Again it is safe to assume that we have to delete both relations A to B and B to A */

	$sql="DELETE FROM relateditems
			WHERE (stockid = '". $Item ."' AND related ='". $_GET['Related'] ."')
			OR (stockid = '". $_GET['Related'] ."' AND related ='". $Item ."')";
	$ErrMsg = _('Could not delete this relationshop');
	$result = DB_query($sql,$ErrMsg);
	prnMsg( _('This relationship has been deleted'),'success');

}

//Always do this stuff

$sql = "SELECT stockmaster.stockid,
			stockmaster.description
		FROM stockmaster, relateditems
		WHERE stockmaster.stockid = relateditems.related
			AND relateditems.stockid='".$Item."'";

$result = DB_query($sql);

if (DB_num_rows($result) > 0) {
	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
	echo '<div>';
	echo '<table class="selection">
			<tr>
				<th colspan="3">
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />' .
				_('Related Items To') . ':
				<input type="text" required="required" autofocus="autofocus" name="Item" size="22" value="' . $Item . '" maxlength="20" />
				<input type="submit" name="NewPart" value="' . _('List Related Items') . '" /></th>
			</tr>';

	echo '<tbody>
			<tr>
				<th class="ascending">' . _('Code') . '</th>
				<th class="ascending">' . _('Description') . '</th>
				<th>' . _('Delete') . '</th>
			</tr>';

	$k=0; //row colour counter
	while ($myrow = DB_fetch_array($result)) {
		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k=1;
		}

		echo   '<td>' . $myrow['stockid'] . '</td>
				<td>' .  $myrow['description'] . '</td>';
		echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?Item=' . $Item . '&amp;Related=' . $myrow['stockid'] . '&amp;delete=yes" onclick="return confirm(\'' . _('Are you sure you wish to delete this relationship?') . '\');">' . _('Delete') . '</a></td>';
		echo '</tr>';

	}
	//END WHILE LIST LOOP
	echo '</tbody></table><br />';
	echo '</div>
		  </form>';
} else {
	prnMsg(_('There are no items related set up for this part'),'warn');
}

echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?Item=' . $Item . '">';
echo '<div>';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
if (isset($_GET['Edit'])){
	/*the price sent with the get is sql format price so no need to filter */
	$_POST['Related'] = $_GET['Related'];
}

echo '<br /><table class="selection">';

echo '<tr><th colspan="5"><h3>' . $Item . ' - ' . $PartDescription . '</h3></th></tr>';

echo '<tr><td>' . _('Related Item Code') . ':</td>
          <td>
          <input type="text" class="text" required="required" name="Related" size="21" maxlength="20" value="';
          if (isset($_POST['Related'])) {
	         echo $_POST['Related'];
          }
          echo '" />
     </td></tr>
</table>
<br /><div class="centre">
<input type="submit" name="submit" value="' . _('Enter') . '/' . _('Amend Relation') . '" />
</div>';


echo '</div>
      </form>';
include('includes/footer.php');

?>