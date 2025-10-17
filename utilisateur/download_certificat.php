<?php
require "../database.php";
require('fpdf/fpdf.php');

$conn = db_connect();

$user_id = $_GET['idUtilisateur'] ?? null;
$event_id = $_GET['idEvenement'] ?? null;

if (!$user_id || !$event_id) {
    die("Paramètres manquants");
}

$sql = "SELECT 
            a.idUtilisateur,
            a.idEvenement,
            a.dateGeneration, 
            a.objet,
            u.nom,
            u.prenom,
            u.filiere,
            e.titre AS evenement_titre,
            e.description AS evenement_description,
            c.nom AS club_nom,
            e.date AS date_evenement,
            e.lieu,
            e.nbrParticipants,
            e.statut
        FROM Attestation a
        JOIN Utilisateur u ON a.idUtilisateur = u.idUtilisateur
        JOIN Evenement e ON a.idEvenement = e.idEvenement
        JOIN Club c ON e.idClub = c.idClub
        WHERE a.idUtilisateur = ? AND a.idEvenement = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
$result = $stmt->get_result();
$attestation = $result->fetch_assoc();

if (!$attestation) {
    die("Certificat non trouvé");
}

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFillColor(248, 249, 250);
        $this->Rect(0, 0, 297, 210, 'F');
        
        
        $this->SetDrawColor(59, 130, 246);
        $this->SetLineWidth(2);
        $this->Rect(10, 10, 277, 190);
        
        $this->SetFont('Arial', 'B', 28);
        $this->SetTextColor(1, 58, 99);
        $this->SetXY(0, 25);
        $this->Cell(297, 15, utf8_decode('CERTIFICAT DE PARTICIPATION'), 0, 1, 'C');
        
        $this->SetDrawColor(59, 130, 246);
        $this->SetLineWidth(0.8);
        $this->Line(50, 45, 247, 45);
    }
    
    function Footer()
    {
    }
}

$pdf = new PDF('L');
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$certificat_blue = array(1, 58, 99);
$name_blue = array(1, 73, 124);
$info_blue = array(97, 165, 194);
$gray = array(107, 114, 128);
$darkGray = array(55, 65, 81);

$pdf->SetY(55);
$pdf->SetFont('Arial', '', 16);
$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
$pdf->MultiCell(0, 10, utf8_decode("NOUS CERTIFIONS PAR LA PRÉSENTE QUE"), 0, 'C');

$pdf->SetY(80);
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetTextColor($name_blue[0], $name_blue[1], $name_blue[2]);

$full_name = utf8_decode(strtoupper($attestation['prenom'] . ' ' . $attestation['nom']));
$name_width = $pdf->GetStringWidth($full_name);
$centered_x = (297 - $name_width) / 2;

$pdf->SetX($centered_x);
$pdf->Cell($name_width, 20, $full_name, 0, 1, 'L');

$line_start_x = $centered_x - 20;
$line_end_x = $centered_x + $name_width + 20;
$pdf->SetDrawColor($name_blue[0], $name_blue[1], $name_blue[2]);
$pdf->SetLineWidth(1);
$pdf->Line($line_start_x, 105, $line_end_x, 105);

$pdf->SetY(115);
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);

$description = utf8_decode("a fait preuve d'un engagement et d'une implication exceptionnels lors de sa participation à l'événement \"");
$description .= utf8_decode($attestation['evenement_titre']) . utf8_decode("\", organisé par le club ");
$description .= utf8_decode($attestation['club_nom']) . utf8_decode(" de l'École Nationale des Sciences Appliquées de Tétouan");

if ($attestation['date_evenement'] && $attestation['date_evenement'] != '0000-00-00') {
    $description .= utf8_decode(" le ") . date('d/m/Y', strtotime($attestation['date_evenement']));
} else {
    $description .= utf8_decode(" le ") . date('d/m/Y');
}

$description .= ".";

$pdf->MultiCell(0, 10, $description, 0, 'C');

$pdf->SetY(145);
$pdf->SetFont('Arial', 'I', 14);
$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);

$location_text = utf8_decode("Fait à Tétouan, le ");
if ($attestation['dateGeneration'] && $attestation['dateGeneration'] != '0000-00-00 00:00:00') {
    $location_text .= date('d/m/Y', strtotime($attestation['dateGeneration']));
} else {
    $location_text .= date('d/m/Y');
}

$location_width = $pdf->GetStringWidth($location_text);
$location_centered_x = (297 - $location_width) / 2;

$pdf->SetX($location_centered_x);
$pdf->Cell($location_width, 10, $location_text, 0, 1, 'L');

$pdf->SetY(165);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor($info_blue[0], $info_blue[1], $info_blue[2]);

$info_text = utf8_decode("Lieu: ") . utf8_decode($attestation['lieu']);
if ($attestation['nbrParticipants'] && $attestation['nbrParticipants'] > 0) {
    $info_text .= utf8_decode(" | Participants: ") . $attestation['nbrParticipants'];
}

$info_width = $pdf->GetStringWidth($info_text);
$info_centered_x = (297 - $info_width) / 2;

$pdf->SetX($info_centered_x);
$pdf->Cell($info_width, 8, $info_text, 0, 1, 'L');

$pdf->SetY(185);

$president_text = utf8_decode("Président du club");
$president_width = $pdf->GetStringWidth($president_text);
$president_centered_x = (297 / 2) - $president_width - 20;

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor($name_blue[0], $name_blue[1], $name_blue[2]);
$pdf->SetX($president_centered_x);
$pdf->Cell($president_width, 12, $president_text, 0, 0, 'L');

$club_text = utf8_decode(strtoupper($attestation['club_nom']));
$club_width = $pdf->GetStringWidth($club_text);
$club_centered_x = (297 / 2) + 20;

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor($name_blue[0], $name_blue[1], $name_blue[2]);
$pdf->SetX($club_centered_x);
$pdf->Cell($club_width, 12, $club_text, 0, 1, 'L');

$pdf->SetY(200);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);

$president_line_start = $president_centered_x - 10;
$president_line_end = $president_centered_x + $president_width + 10;
$pdf->Line($president_line_start, 200, $president_line_end, 200);

$club_line_start = $club_centered_x - 10;
$club_line_end = $club_centered_x + $club_width + 10;
$pdf->Line($club_line_start, 200, $club_line_end, 200);

$pdf->SetY(210);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($certificat_blue[0], $certificat_blue[1], $certificat_blue[2]);
$pdf->Cell(297, 10, date('Y'), 0, 1, 'C');

$filename = "certificat_" . $attestation['prenom'] . "_" . $attestation['nom'] . "_" . $attestation['evenement_titre'] . ".pdf";
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

$pdf->Output('D', $filename);

$conn->close();
?>