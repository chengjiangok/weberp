<?php
/*
 * @Descripttion: WebERP开发升级
 * @version: 202003
 * @Author: ChengJiang
 * @Date: 2020-02-18 20:16:56
 * @LastEditors: ChengJiang
 * @LastEditTime: 2020-04-06 13:47:23
 */
/* $Id: CustomerBalancesMovement.php 6941 2014-10-26 23:18:08Z daintree $*/

include('includes/session.php');
$Title='客户交易汇总查询';//_('Customer Activity and Balances');
/*To do: Info in the manual. RChacon.
$ViewTopic = '';// Filename in ManualContents.php's TOC.
$BookMark = '';// Anchor's id in the manual's html document.*/

if(!isset($_POST['CSV'])) {
	include('includes/header.php');
	echo '<p class="page_title_text"><img alt="" src="'.$RootPath.'/css/'.$Theme.'/images/transactions.png" title="' . $Title . '" /> ' .$Title . '</p>';
}

if (!isset($_POST['CSV'])||isset($_POST['RunReport'])){

	$SalesAreasResult = DB_query("SELECT areacode, areadescription FROM areas");
	$CustomersResult = DB_query("SELECT debtorno, name FROM debtorsmaster ORDER BY name");
	$SalesFolkResult = DB_query("SELECT salesmancode, salesmanname FROM salesman ORDER BY salesmanname");

	echo '<form id="Form1" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" method="post">

		 <div>
			<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />

			<table cellpadding="2" class="selection">
				<tr>
					<td>' . _('Customer') . '</td>
					<td><select name="Customer">
						<option selected="selected" value="">' . _('All') . '</option>';
	while ($CustomerRow = DB_fetch_array($CustomersResult)) {
		echo 			'<option value="' . $CustomerRow['debtorno'] . '">' . $CustomerRow['name'] . '</option>';
	}
	echo 			'</select>
					</td>
				</tr>
				<tr>
					<td>' . _('Sales Area') . '</td>
					<td><select name="SalesArea">
						<option selected="selected" value="">' . _('All') . '</option>';
	while ($AreaRow = DB_fetch_array($SalesAreasResult)) {
		echo 			'<option value="' . $AreaRow['areacode'] . '">' . $AreaRow['areadescription'] . '</option>';
	}
	echo 			'</select>
					</td>
				</tr>
				<tr>
					<td>' . _('Sales Person') . '</td>
					<td><select name="SalesPerson">
						<option selected="selected" value="">' . _('All') . '</option>';
	while ($SalesPersonRow = DB_fetch_array($SalesFolkResult)) {
		echo 			'<option value="' . $SalesPersonRow['salesmancode'] . '">' . $SalesPersonRow['salesmanname'] . '</option>';
	}
	echo 			'</select>
					</td>
				</tr>
				<tr>
					<td>' . _('Date From') . ':</td>
					<td><input type="text" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="FromDate" maxlength="10" size="11" value="' . Date($_SESSION['DefaultDateFormat'], Mktime(0, 0, 0, Date('m') - $_SESSION['NumberOfMonthMustBeShown'], Date('d'), Date('Y'))) . '" /></td>
				</tr>
				<tr>
					<td>' . _('Date To') . ':</td>
					<td><input type="text" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="ToDate" maxlength="10" size="11" value="' . Date($_SESSION['DefaultDateFormat']) . '" /></td>
				</tr>
			</table>
			<br />
			<div class="centre">
				<input tabindex="5" type="submit" name="RunReport" value="查询客户余额" />
				<input tabindex="4" type="submit" name="CSV" value="导出CSV" />
				
			</div>
		 </div>

	<br />';
//	include('includes/footer.php');
	//exit;
}

if ($_POST['Customer']!='') {
	$WhereClause = "debtorsmaster.debtorno='" . $_POST['Customer'] . "'";
} elseif ($_POST['SalesArea']!='') {
	$WhereClause = "debtorsmaster.area='" . $_POST['SalesArea'] . "'";
} elseif ($_POST['SalesPerson']!='') {
	$WhereClause = "debtorsmaster.salesman='" . $_POST['SalesPerson'] . "'";
}

$sql = "SELECT SUM(ovamount+ovgst+ovdiscount+ovfreight-alloc) AS currencybalance,
				debtorsmaster.debtorno,
				debtorsmaster.name,
				decimalplaces AS currdecimalplaces,
				SUM((ovamount+ovgst+ovdiscount+ovfreight-alloc)/debtortrans.rate) AS localbalance
		FROM debtortrans INNER JOIN debtorsmaster
			ON debtortrans.debtorno=debtorsmaster.debtorno
		INNER JOIN currencies
		ON debtorsmaster.currcode=currencies.currabrev";

if (mb_strlen($WhereClause)>0){
	$sql .= " WHERE " . $WhereClause . " ";
}
	$sql .= " GROUP BY debtorsmaster.debtorno";

	$result = DB_query($sql);

	$LocalTotal =0;
	$CountLine=DB_num_rows($result);
//prnMsg($CountLine);//$sql);
if (isset($_POST['CSV'])&&$CountLine>0){
	$CSVFile = '"' . _('Customer') . '","' . _('Opening Balance') . '","' . _('Debits') . '", "' . _('Credits') . '","' . _('Balance') . '"' . "\n";
}
if (isset($_POST['RunReport'])&&$CountLine>0){
		echo '<table>
				<tr>
					<th class="ascending">' . _('Customer') . ' </th>
					<th class="ascending">' . _('Opening Balance') . '</th>
					<th class="ascending">' . _('Debits') . '</th>
					<th class="ascending">' . _('Credits') . '</th>
					<th class="ascending">' . _('Balance') . '</th>
				</tr>';

}
	$OpeningBalances =0;
	$Debits =0;
	$Credits =0;
	$ClosingBalances =0;

	while ($myrow=DB_fetch_array($result)){

		/*Get the sum of all transactions after the ending date -
		* we need to take off the sum of all movements after the ending date from the current balance calculated above
		* to get the balance as at the end of the period
		*/
		$sql = "SELECT SUM(ovamount+ovgst+ovdiscount+ovfreight) AS currencytotalpost,
						debtorsmaster.debtorno,
						SUM((ovamount+ovgst+ovdiscount+ovfreight)/debtortrans.rate) AS localtotalpost
				FROM debtortrans INNER JOIN debtorsmaster
					ON debtortrans.debtorno=debtorsmaster.debtorno
				WHERE trandate > '" . FormatDateForSQL($_POST['ToDate']) . "'
				AND debtorsmaster.debtorno = '" . $myrow['debtorno'] . "'
				GROUP BY debtorsmaster.debtorno";

		$TransPostResult = DB_query($sql);
		$TransPostRow = DB_fetch_array($TransPostResult);
		/* Now we need to get the debits and credits during the period under review
		*/
		$sql = "SELECT SUM(CASE WHEN debtortrans.type=10 THEN ovamount+ovgst+ovdiscount+ovfreight ELSE 0 END) AS currencydebits,
						SUM(CASE WHEN debtortrans.type<>10 THEN ovamount+ovgst+ovdiscount+ovfreight ELSE 0 END) AS currencycredits,
						debtorsmaster.debtorno,
						SUM(CASE WHEN debtortrans.type=10 THEN (ovamount+ovgst+ovdiscount+ovfreight)/debtortrans.rate ELSE 0 END) AS localdebits,
						SUM(CASE WHEN debtortrans.type<>10 THEN (ovamount+ovgst+ovdiscount+ovfreight)/debtortrans.rate ELSE 0 END) AS localcredits
				FROM debtortrans INNER JOIN debtorsmaster
					ON debtortrans.debtorno=debtorsmaster.debtorno
				WHERE trandate>='" . FormatDateForSQL($_POST['FromDate']) . "' AND trandate <= '" . FormatDateForSQL($_POST['ToDate']) . "'
				AND debtorsmaster.debtorno = '" . $myrow['debtorno'] . "'
				GROUP BY debtorsmaster.debtorno";

		$TransResult = DB_query($sql);
		$TransRow = DB_fetch_array($TransResult);

		$OpeningBal = $myrow['localbalance']-$TransPostRow['localtotalpost']-$TransRow['localdebits']-$TransRow['localcredits'];
		$ClosingBal = $myrow['localbalance']-$TransPostRow['localtotalpost'];
		if($OpeningBal !=0 OR $ClosingBal!=0 OR $TransRow['localdebits']!=0 OR $TransRow['localcredits']!=0) {

			if (!isset($_POST['CSV'])){
				if ($K==1){
					echo '<tr class="EvenTableRows">';
					$K=0;
					} else {
					echo '<tr class="OddTableRows">';
					$K=1;
					}
				echo '
						<td>' . $myrow['name'] . ' </td>
						<td class="number">' . locale_number_format($OpeningBal,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($TransRow['localdebits'],$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($TransRow['localcredits'],$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($ClosingBal,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					</tr>';
		
			}else { //send the line to CSV file
				$CSVFile .=  '"' . stripcomma($myrow['name']) . '","' . stripcomma($OpeningBal) . '","' . stripcomma($TransRow['localdebits']) . '","' . stripcomma($TransRow['localcredits']) . '","' . stripcomma($ClosingBal) . '"' . "\n";

			}

			$OpeningBalances += $OpeningBal;
			$Debits += $TransRow['localdebits'];
			$Credits += $TransRow['localcredits'];
			$ClosingBalances += $ClosingBal;
		}

	}
	if (isset($_POST['RunReport'])&&$CountLine>0){
		if ($_POST['Customer']==''){ //if there could be several customers being reported、
	
			
				/*echo '<table>
					<tr>
						<th></th>
						<th>' . _('Opening Balance') . '</th>
						<th>' . _('Debits') . '</th>
						<th>' . _('Credits') . '</th>
						<th>' . _('Balance') . '</th>
					</tr>*/
				echo'<tr>
						<td>总计</td>
						<td class="number">' . locale_number_format($OpeningBalances,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($Debits,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($Credits,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
						<td class="number">' . locale_number_format($ClosingBalances,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
					</tr>';
				//</table>';
		
		}
		echo '</table>';
	}


	


if (isset($_POST['CSV'])&&$CountLine>0){
	

	$CSVFile .=  '"' . _('TOTALS') . '","' . stripcomma($OpeningBalances) . '","' . stripcomma($Debits) . '","' . stripcomma($Credits) . '","' . stripcomma($ClosingBalances) . '"' . "\n";


	header('Content-Encoding: UTF-8');
    header('Content-type: text/csv; charset=UTF-8');
    header("Content-disposition: attachment; filename=C". $_SESSION['CompanyRecord'][$_SESSION['Tag']]['coyname']."CustomerBalance_" .  FormatDateForSQL(date("Ymd")) .'.csv');
    header("Pragma: public");
    header("Expires: 0");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
	echo $CSVFile;
	exit;
}
echo'</form>';
include('includes/footer.php');

function stripcomma($str) { //because we're using comma as a delimiter
	return str_replace(',', '', $str);
}
?>