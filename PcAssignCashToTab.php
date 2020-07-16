<?php
/* $Id: PcAssignCashToTab.php 7482 2016-04-01 08:36:03Z exsonqu $*/

include('includes/session.php');
$Title = '付款申请';
/* webERP manual links before header.php */
$ViewTopic= 'PettyCash';
$BookMark = 'CashAssignment';
include('includes/header.php');

if (isset($_POST['SelectedTabs'])){
	$SelectedTabs = mb_strtoupper($_POST['SelectedTabs']);
} elseif (isset($_GET['SelectedTabs'])){
	$SelectedTabs = mb_strtoupper($_GET['SelectedTabs']);
}

if (isset($_POST['SelectedIndex'])){
	$SelectedIndex = $_POST['SelectedIndex'];
} elseif (isset($_GET['SelectedIndex'])){
	$SelectedIndex = $_GET['SelectedIndex'];
}

if (isset($_POST['Days'])){
	$Days = $_POST['Days'];
} elseif (isset($_GET['Days'])){
	$Days = $_GET['Days'];
}

if (isset($_POST['Cancel'])) {
	unset($SelectedTabs);
	unset($SelectedIndex);
	unset($Days);
	unset($_POST['Amount']);
	unset($_POST['Notes']);
	unset($_POST['Receipt']);
}

if (isset($_POST['Process'])) {
	if ($SelectedTabs=='') {
		prnMsg(_('You Must First Select a Petty Cash Tab To Assign Cash'),'error');
		unset($SelectedTabs);
	}
}

if (isset($_POST['Go'])) {
	$InputError = 0;
	if ($Days<=0) {
		$InputError = 1;
		prnMsg(_('The number of days must be a positive number'),'error');
		$Days=30;
	}
}

if (isset($_POST['submit'])) {
	//initialise no input errors assumed initially before we test
	$InputError = 0;

	echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/money_add.png" title="' .
		_('Search') . '" alt="" />' . ' ' . $Title. '</p>';

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	$i=1;

	if ($_POST['Amount']==0) {
		$InputError = 1;
		prnMsg('<br />' . _('The Amount must be input'),'error');
	}

	$sqlLimit = "SELECT pctabs.tablimit,
					pctabs.currency,
					currencies.decimalplaces
				FROM pctabs,
					currencies
				WHERE pctabs.currency = currencies.currabrev
					AND pctabs.tabcode='" . $SelectedTabs . "'";

	$ResultLimit = DB_query($sqlLimit);
	$Limit=DB_fetch_array($ResultLimit);

	if (($_POST['CurrentAmount'])>$Limit['tablimit']){
		$InputError = 1;
		prnMsg(_('Cash NOT assigned because PC tab current balance is over its cash limit of') . ' ' . locale_number_format($Limit['tablimit'],$Limit['decimalplaces']) . ' ' . $Limit['currency'],'error');
		prnMsg(_('Report expenses before being allowed to assign more cash or ask the administrator to increase the limit'),'error');
	}

	if ($InputError !=1 AND (($_POST['CurrentAmount']+$_POST['Amount'])>$Limit['tablimit'])){
		prnMsg(_('Cash assigned but PC tab current balance is over its cash limit of') . ' ' . locale_number_format($Limit['tablimit'],$Limit['decimalplaces']) . ' ' . $Limit['currency'],'warning');
		prnMsg(_('Report expenses before being allowed to assign more cash or ask the administrator to increase the limit'),'warning');
	}

	if ($InputError !=1 AND isset($SelectedIndex) ) {

		$sql = "UPDATE pcashdetails
				SET date = '".FormatDateForSQL($_POST['Date'])."',
					amount = '" . filter_number_format($_POST['Amount']) . "',
					authorized = '0000-00-00',
					notes = '" . $_POST['Notes'] . "',
					receipt = '" . $_POST['Receipt'] . "'
				WHERE counterindex = '" . $SelectedIndex . "'";
		$msg = _('Assignment of cash to PC Tab ') . ' ' . $SelectedTabs . ' ' .  _('has been updated');

	} elseif ($InputError !=1 ) {
		// Add new record on submit
		$sql = "INSERT INTO pcashdetails
					(counterindex,
					tabcode,
					date,
					codeexpense,
					amount,
					authorized,
					posted,
					notes,
					receipt)
			VALUES (NULL,
					'" . $_POST['SelectedTabs'] . "',
					'".FormatDateForSQL($_POST['Date'])."',
					'ASSIGNCASH',
					'" . filter_number_format($_POST['Amount']) . "',
					'0000-00-00',
					'0',
					'" . $_POST['Notes'] . "',
					'" . $_POST['Receipt'] . "'
					)";
		$msg = _('Assignment of cash to PC Tab ') . ' ' . $_POST['SelectedTabs'] .  ' ' . _('has been created');
	}

	if ( $InputError !=1) {
		//run the SQL from either of the above possibilites
		$result = DB_query($sql);
		prnMsg($msg,'success');
		unset($_POST['SelectedExpense']);
		unset($_POST['Amount']);
		unset($_POST['Notes']);
		unset($_POST['Receipt']);
		unset($_POST['SelectedTabs']);
		unset($_POST['Date']);
	}

} elseif ( isset($_GET['delete']) ) {

	echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/money_add.png" title="' .
		_('Search') . '" alt="" />' . ' ' . $Title. '</p>';
	$sql="DELETE FROM pcashdetails
		WHERE counterindex='" . $SelectedIndex . "'";
	$ErrMsg = _('The assignment of cash record could not be deleted because');
	$result = DB_query($sql,$ErrMsg);
	prnMsg(_('Assignment of cash to PC Tab ') .  ' ' . $SelectedTabs  . ' ' . _('has been deleted') ,'success');
	unset($_GET['delete']);
}

//if (!isset($SelectedTabs)){
if($kv==22){
	/* It could still be the second time the page has been run and a record has been selected for modification - SelectedTabs will exist because it was sent with the new call. If its the first time the page has been displayed with no parameters
	then none of the above are true and the list of sales types will be displayed with
	links to delete or edit each. These will call the same page again and allow update/input
	or deletion of the records*/
	echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/money_add.png" title="' .
		_('Search') . '" alt="" />' . ' ' . $Title. '</p>';

	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
    echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	$SQL = "SELECT tabcode, assigner
			FROM pctabs
			WHERE assigner LIKE '%" . $_SESSION['UserID'] . "%'
			ORDER BY tabcode";

	$result = DB_query($SQL);

    echo '<br /><table class="selection">'; //Main table

	echo '<tr>
	        <td>' . _('Petty Cash Tab To Assign Cash') . ':</td>
            <td><select name="SelectedTabs">';
	while ($myrow = DB_fetch_array($result)) {
		$Assigner = explode(',',$myrow['assigner']);
		if (in_array($_SESSION['UserID'],$Assigner)) {
			if (isset($_POST['SelectTabs']) and $myrow['tabcode']==$_POST['SelectTabs']) {
				echo '<option selected="selected" value="';
			} else {
				echo '<option value="';
			}
			echo $myrow['tabcode'] . '">' . $myrow['tabcode'] . '</option>';
		}
	}

	echo '</select></td></tr>';
   	echo '</table>'; // close main table
    DB_free_result($result);

	echo '<br />
		<div class="centre">
			<input type="submit" name="Process" value="' . _('Accept') . '" />
			<input type="submit" name="Cancel" value="' . _('Cancel') . '" />
		</div>';
	echo '</div>
          </form>';
}

//end of ifs and buts!
//if (isset($_POST['Process']) OR isset($SelectedTabs)) {

	if (!isset($_POST['submit'])) {
		echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/money_add.png" title="' .
			_('Search') . '" alt="" />' . ' ' . $Title. '</p>';
	}
	//echo '<br /><div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">' . _('Select another tab') . '</a></div>';



	if (! isset($_GET['edit']) OR isset ($_POST['GO'])){

		if (isset($_POST['Cancel'])) {
			unset($_POST['Amount']);
			unset($_POST['Date']);
			unset($_POST['Notes']);
			unset($_POST['Receipt']);
		}

		if(!isset ($Days)){
			$Days=30;
		 }

		/* Retrieve decimal places to display */
		$SqlDecimalPlaces="SELECT decimalplaces
					FROM currencies,pctabs
					WHERE currencies.currabrev = pctabs.currency
						AND tabcode='" . $SelectedTabs . "'";
		$result = DB_query($SqlDecimalPlaces);
		$myrow=DB_fetch_array($result);
		$CurrDecimalPlaces = $myrow['decimalplaces'];
         //tabcode='" . $SelectedTabs . "'			AND  
		$sql = "SELECT * FROM pcashdetails
				WHERE  date >=DATE_SUB(CURDATE(), INTERVAL " . $Days . " DAY)
				ORDER BY date, counterindex ASC";
		$result = DB_query($sql);

		echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">
			<div>
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
				<table class="selection">
				<tr>
					<th colspan="11">' . _('Detail Of PC Tab Movements For Last') .':
						<input type="hidden" name="SelectedTabs" value="' . $SelectedTabs . '" />
						<input type="text" class="number" name="Days" value="' . $Days  . '" maxlength="3" size="4" /> ' . _('Days') . '
						<input type="submit" name="Go" value="' . _('Go') . '" /></th>
				</tr>
				<tr>
					<th>' . _('Date') . '</th>
					<th>职员ID</th>
					<th>支款人姓名</th>
					<th>' . _('Expense Code') . '</th>
					<th>' . _('Amount') . '</th>
					<th>' . _('Authorised') . '</th>
					<th>' . _('Notes') . '</th>
					<th>' . _('Receipt') . '</th>
					<th>过账</th>
					<th></th>
					<th></th>
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

		$sqldes="SELECT description
					FROM pcexpenses
					WHERE codeexpense='". $myrow['3'] . "'";

		$ResultDes = DB_query($sqldes);
		$Description=DB_fetch_array($ResultDes);

		if (!isset($Description['0'])){
			$Description['0']='ASSIGNCASH';
		}

		if (($myrow['authorized'] == '0000-00-00') and ($Description['0'] == 'ASSIGNCASH')){
			// only cash assignations NOT authorized can be modified or deleted
			echo '<td>' . ConvertSQLDate($myrow['date']) . '</td>
			<td>' . $myrow['empid'] . '</td>
			<td>' . $myrow['empid'] . '</td>
				<td>' . $Description['0'] . '</td>
				<td class="number">' . locale_number_format($myrow['amount'],$CurrDecimalPlaces) . '</td>
				<td>' . ConvertSQLDate($myrow['authorized']) . '</td>
				<td>' . $myrow['notes'] . '</td>
				<td>' . $myrow['receipt'] . '</td>
				<td>' . $myrow['posted'] . '</td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?SelectedIndex=' . $myrow['counterindex'] . '&amp;SelectedTabs=' .
					$SelectedTabs . '&amp;Days=' . $Days . '&amp;edit=yes">' . _('Edit') . '</a></td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?SelectedIndex=' . $myrow['counterindex'] . '&amp;SelectedTabs=' .
					$SelectedTabs . '&amp;Days=' . $Days . '&amp;delete=yes" onclick="return confirm(\'' .
						_('Are you sure you wish to delete this code and the expense it may have set up?') . '\');">' .
							_('Delete') . '</a></td>
				</tr>';
		}else{
			echo '<td>' . ConvertSQLDate($myrow['date']) . '</td>
			<td>' . $myrow['empid'] . '</td>
			<td>' . $myrow['empid'] . '</td>
				<td>' . $Description['0'] . '</td>
				<td class="number">' . locale_number_format($myrow['amount'],$CurrDecimalPlaces) . '</td>
				<td>' . ConvertSQLDate($myrow['authorized']) . '</td>
				<td>' . $myrow['notes'] . '</td>
				<td>' . $myrow['receipt'] . '</td>
				<td>' . $myrow['posted'] . '</td>
				</tr>';
		}
	}
		//END WHILE LIST LOOP

		$sqlamount="SELECT sum(amount)
					FROM pcashdetails
					WHERE tabcode='".$SelectedTabs."'";

		$ResultAmount = DB_query($sqlamount);
		$Amount=DB_fetch_array($ResultAmount);

		if (!isset($Amount['0'])) {
			$Amount['0']=0;
		}

		echo '<tr>
				<td colspan="2" style="text-align:right"><b>' . _('Current balance') . ':</b></td>
				<td>' . locale_number_format($Amount['0'],$CurrDecimalPlaces) . '</td></tr>';

		echo '</table>';
        echo '</div>
              </form>';
	}

	if (! isset($_GET['delete'])) {

		if (!isset($Amount['0'])) {
			$Amount['0']=0;
		}

		echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') .'">
			<div>
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		if ( isset($_GET['edit'])) {

		$sql = "SELECT * FROM pcashdetails
				WHERE counterindex='".$SelectedIndex."'";

			$result = DB_query($sql);
			$myrow = DB_fetch_array($result);

			$_POST['Date'] = ConvertSQLDate($myrow['date']);
			$_POST['SelectedExpense'] = $myrow['codeexpense'];
			$_POST['Amount']  = $myrow['amount'];
			$_POST['Notes']  = $myrow['notes'];
			$_POST['Receipt']  = $myrow['receipt'];

			echo '<input type="hidden" name="SelectedTabs" value="' . $SelectedTabs . '" />
				<input type="hidden" name="SelectedIndex" value="' . $SelectedIndex. '" />
				<input type="hidden" name="CurrentAmount" value="' . $Amount[0]. '" />
				<input type="hidden" name="Days" value="' . $Days . '" />';
		}

/* Ricard: needs revision of this date initialization */
		if (!isset($_POST['Date'])) {
			$_POST['Date']=Date($_SESSION['DefaultDateFormat']);
		}

        echo '<br />
				<table class="selection">'; //Main table
        if (isset($_GET['SelectedIndex'])) {
            echo '<tr>
					<th colspan="2"><h3>' . _('Update Cash Assignment') . '</h3></th>
				</tr>';
        } else {
            echo '<tr>
					<th colspan="2"><h3>现金申请</h3></th>
				</tr>';
        }
		echo '<tr>
				<td>' . _('Cash Assignation Date') . ':</td>
				<td><input type="text" class="date" alt="'.$_SESSION['DefaultDateFormat'].'" name="Date" required="required" autofocus="autofocus" size="10" maxlength="10" value="' . $_POST['Date'] . '" /></td>
			</tr>';


		if (!isset($_POST['Amount'])) {
			$_POST['Amount']=0;
		}
		 // $sql="SELECT `empid`, `empname` FROM `employfile` ";
		  $result=DB_query($sql);
		echo '<tr>
				<td>收款人:</td>
				<td><select name="custType">';
				while ($myrow = DB_fetch_array($result)) {
				
					if (isset($_POST['custType']) and $myrow['empid']==$_POST['custType']) {
						echo '<option selected="selected" value="';
					} else {
						echo '<option value="';
					}
					echo $myrow['empid'] . '">' . $myrow['empname'] . '</option>';
				
			}
		
			echo '</select></td></tr>';
			$sql="SELECT codeexpense, description, glaccount, tag FROM pcexpenses";
            $result=DB_query($sql);
		echo '<tr>
				<td>付款类别:</td>
			    <td><select name="PaymentType">';
		while ($myrow = DB_fetch_array($result)) {
		
			if (isset($_POST['PaymentType']) and $myrow['codeexpense']==$_POST['PaymentType']) {
				echo '<option selected="selected" value="';
			} else {
				echo '<option value="';
			}
			echo $myrow['codeexpense'] . '">' . $myrow['description'] . '</option>';
		
	}

	echo '</select></td></tr>';
	$sql = "SELECT 	bankaccountname,bankaccountnumber,	accountcode,importformat	FROM bankaccounts
				WHERE importformat <>''";
		$result = DB_query($sql);
	echo '<tr>
					<td>付款账户:</td>
					<td><select name="BankAccount">';
				   while ($myrow=DB_fetch_array($result,$db)){
					   
					   if (isset($_POST['BankAccount']) and ($myrow['accountcode'].'^'.$myrow['bankaccountnumber']==$_POST['BankAccount'])){
							   echo '<option selected="selected" value="' ;
						   }else {
							   if (!isset($_POST['BankAccount'])){
									  $_POST['BankAccount']=$myrow['accountcode'].'^'.$myrow['bankaccountnumber']; 
							   }
							   echo '<option value ="';
						   }						
						   echo  $myrow['accountcode'] .'^'.$myrow['bankaccountnumber']. '">' . $myrow['bankaccountname'] . '</option>';
					   }
			   
		echo '</select></td>
				</tr>';
		echo '<tr>
				<td>' . _('Amount') . ':</td>
				<td><input type="text" class="number" name="Amount" size="12" maxlength="11" value="' . locale_number_format($_POST['Amount'],$CurrDecimalPlaces) . '" /></td>
			</tr>';

		if (!isset($_POST['Notes'])) {
			$_POST['Notes']='';
		}

		echo '<tr>
				<td>' . _('Notes') . ':</td>
				<td><input type="text" name="Notes" size="50" maxlength="49" value="' . $_POST['Notes'] . '" /></td>
			</tr>';

		if (!isset($_POST['Receipt'])) {
			$_POST['Receipt']='';
		}
		/*
		echo '<tr>
				<td>' . _('Receipt') . ':</td>
				<td><input type="text" name="Receipt" size="50" maxlength="49" value="' . $_POST['Receipt'] . '" /></td>
			</tr>';*/
			
		echo'	</table>
			<input type="hidden" name="CurrentAmount" value="' . $Amount['0']. '" />
			<input type="hidden" name="SelectedTabs" value="' . $SelectedTabs . '" />
			<input type="hidden" name="Days" value="' .$Days. '" />
			<br />
			<div class="centre">
				<input type="submit" name="submit" value="' . _('Accept') . '" />
				<input type="submit" name="Cancel" value="' . _('Cancel') . '" /></div>
			</div>
		</form>';

	} // end if user wish to delete
//}

include('includes/footer.php');
?>