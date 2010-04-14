// Please note, this is not a *complete* example. It will not run as-is. Just a few sample calls to the library.
// Creates a table with 2 rows, then publishes the PDF.
// The require statments are arbitrary, I simply included them to make you aware you need to include the path to those
// files.

require_once 'Zend_Helper/Pdf.php';
require_once 'Zend_Helper/Pdf/Table.php';
$pdf = new ZendPDF_Helper_Pdf();

$table = $pdf->addTable(array('align'=>'justify'));
$table->addSpacerRow();
$table->addRow()
          ->addCol("Evaluation", array('bold' => true, 'colspan' => 7, 'align' => 'center'));
$table->addRow()
          ->addCol("Right Carotid", array('bold' => true, 'align' => 'center', 'colspan'=>3))
          ->addCol(" ")
          ->addCol("Left Carotid", array('bold' => true, 'align' => 'center', 'colspan'=>3));

$pdf->build();