<?php
require_once '../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Enable remote assets (if any)
$options = new Options();
$options->set('isRemoteEnabled', true);

// Instantiate Dompdf
$dompdf = new Dompdf($options);

// Start output buffering to capture the HTML from receipt.php
ob_start();
include 'receipt.php'; // This should echo valid HTML receipt
$html = ob_get_clean();

// Load the HTML content into Dompdf
$dompdf->loadHtml($html);

// Setup paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML to PDF
$dompdf->render();

// Stream the generated PDF to browser (inline)
$dompdf->stream("receipt_" . date("Ymd_His") . ".pdf", ["Attachment" => false]);
exit;
