
<?php

/* $Id$ $Revision: 1.5 $ */
/*
 * @Author: ChengJiang 
 * @Date: 2018-03-25 07:03:50 
 * @Last Modified by: ChengJiang
 * @Last Modified time: 2018-05-13 19:24:00
 */

include('includes/session.php');
include('includes/tcpdf/PDFJournal.php');
if (isset($_POST['JournalNo'])) {
	$str=explode('^',$_POST['JournalNo']);
}else if (isset($_GET['JournalNo'])) {
	$str=explode('^',$_GET['JournalNo']);
 
} 
if ($str!='') {
	$PrintNO=$str[1];
	$periodno=$str[0];
	$TagsGroup=$_SESSION['tagsgroup'][$str[2]];
}
prnMsg($_GET['JournalNo'].$TagsGroup.'=');


	
	$sql="SELECT gltrans.typeno,
					systypes.typename,
					gltrans.trandate,
					gltrans.transno,
					abs(gltrans.printno) printno,
					gltrans.account,
					chartmaster.accountname,
					gltrans.narrative,
					toamount(gltrans.amount,-1,0,0,1,gltrans.flg) AS Debits,
					toamount(gltrans.amount,-1,0,0,-1,gltrans.flg) AS Credits		
				FROM gltrans
				LEFT JOIN chartmaster	ON gltrans.account=chartmaster.accountcode			
				LEFT JOIN systypes	ON gltrans.type=systypes.typeid
				WHERE gltrans.periodno='".$periodno."' 	AND abs(gltrans.printno)=" . $PrintNO . "
				   AND gltrans.tag IN ( ".$TagsGroup[0]." )	 ORDER BY abs(gltrans.printno),gltrans.typeno";
	//echo $sql;
//	LEFT JOIN tags	ON gltrans.tag=tags.tagref
$result = DB_query($sql);//,$ErrMsg,_('The SQL that failed was'),true);		
//prnMsg($sql);
//exit;
$row=DB_num_rows($result);
if ($row>1){
	/*$sql="SELECT  confvalue FROM myconfig WHERE confname='prtformat'";
	$confresult=DB_query($sql);
	$row=DB_fetch_row($confresult);

	$prtformat = json_decode($row[0],true);*/
	$prtformat=array("lp"=>"L","format"=>'A5',"top"=>5,"prtrow"=>15);
	// create new PDF document PDF_PAGE_ORIENTATION  PDF_PAGE_FORMAT
$pdf = new MYPDF('L', PDF_UNIT, 'A5');//, true, 'UTF-8', false);
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('chengjiang');
$pdf->SetTitle( _('Account Voucher'));
$pdf->SetSubject( _('Account Vouche') );
$pdf->SetKeywords('TCPDF, PDF');
// set default header dataPDF_HEADER_TITLE.
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
// set margins
//	$pdf->setPageFormat('A5', 'P')
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT,true);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetHeaderMargin(0);
//$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetFooterMargin(0); 
$pdf->setPrintFooter(false);
$pdf->setPrintHeader(false);
// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 0);//PDF_MARGIN_BOTTOM);
// set image scale factor

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/tcpdf/chi.php')) {
	require_once(dirname(__FILE__).'/tcpdf/chi.php');
	$pdf->setLanguageArray($l);
}
// set font helvetica 
$pdf->SetFont('droidsansfallback', '', 10);
// add a page
$pdf->AddPage();
// column titles
$header ='';// array(_('Sequence'), _('Date'), _('Voucher No'), _('Abstract'),_('Account Code'), _('Detailed Account'), _('Debits'), _('Credit'));
// print colored table		
$pdf->PDFJournal($result,$header,$periodno,$prtformat,$TagsGroup);
// END OF FILE
		ob_end_clean();
			// close and output PDF document
			$ym= date('Y-m',strtotime('-'.($_SESSION['period']-$periodno).' Month',strtotime($_SESSION['lastdate'])));
			$pdffilename=$ym.'会计凭证打印号'.$PrintNO.'.pdf';
		
			$pdf->Output($pdffilename,'D');
			$pdf->__destruct();

		//	$sql="update  gltrans set printno=abs(printno) where  periodno=".$periodno." and abs(printno)=".$PrintNO." printno<0";
		
		//	$result = DB_query($sql);
	
	//exit;
}
?>