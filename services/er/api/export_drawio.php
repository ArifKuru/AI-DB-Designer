<?php
// /services/api/export_drawio.php

// Hata Gösterimi Kapat (Dosya bozulmasın)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once $_SERVER["DOCUMENT_ROOT"] . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) die("Unauthorized");

$project_id = $_GET['id'] ?? null;
if (!$project_id) die("Missing ID");

try {
    // 1. VERİLERİ ÇEK
    $stmtP = $db->prepare("SELECT name FROM projects WHERE id = ?");
    $stmtP->execute([$project_id]);
    $project = $stmtP->fetch();
    $projectName = $project ? $project['name'] : "Project";

    $stmtTables = $db->prepare("SELECT * FROM project_tables WHERE project_id = ?");
    $stmtTables->execute([$project_id]);
    $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

    $tableColumns = [];
    foreach($tables as $t) {
        $stmtCols = $db->prepare("SELECT * FROM project_columns WHERE table_id = ?");
        $stmtCols->execute([$t['id']]);
        $tableColumns[$t['id']] = $stmtCols->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmtRels = $db->prepare("SELECT * FROM project_relationships WHERE project_id = ?");
    $stmtRels->execute([$project_id]);
    $relationships = $stmtRels->fetchAll(PDO::FETCH_ASSOC);

    // 2. XML BAŞLANGICI
    $xml = new SimpleXMLElement('<mxfile host="Electron" type="device"></mxfile>');
    $diagram = $xml->addChild('diagram');
    $diagram->addAttribute('name', 'ER Diagram');

    // Grid ve rehber çizgileri açık
    $model = $diagram->addChild('mxGraphModel');
    $model->addAttribute('dx', '1200');
    $model->addAttribute('dy', '1200');
    $model->addAttribute('grid', '1');
    $model->addAttribute('gridSize', '10');
    $model->addAttribute('guides', '1');
    $model->addAttribute('tooltips', '1');
    $model->addAttribute('connect', '1');
    $model->addAttribute('arrows', '1');

    $root = $model->addChild('root');

    $mxCell0 = $root->addChild('mxCell');
    $mxCell0->addAttribute('id', '0');
    $mxCell1 = $root->addChild('mxCell');
    $mxCell1->addAttribute('id', '1');
    $mxCell1->addAttribute('parent', '0');

    // 3. STİL AYARLARI (Viz.js ile Aynı Tema)
    // Tablo Başlığı: Koyu İndigo (#1e293b), Beyaz Yazı
    $swimlaneStyle = "shape=swimlane;startSize=30;horizontal=1;childLayout=stackLayout;horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=1;marginBottom=0;whiteSpace=wrap;html=1;" .
        "fillColor=#1e293b;fontColor=#ffffff;strokeColor=#1e293b;fontStyle=1;fontSize=14;rounded=1;arcSize=10;";

    // Sütunlar: Beyaz Arkaplan, Gri Çizgi
    $colStyleBase = "text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=10;spacingRight=10;overflow=hidden;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;rotatable=0;whiteSpace=wrap;html=1;fontSize=12;";

    // PK Rengi (Amber) ve FK Rengi (Blue)
    $pkColor = "#b45309";
    $fkColor = "#1d4ed8";

    // 4. TABLOLARI OLUŞTUR (Grid Layout)
    $xPos = 40;
    $yPos = 40;
    $colCounter = 0;
    $maxColsPerRow = 4;
    $tableWidth = 220; // Biraz daha geniş
    $rowHeight = 0;

    foreach ($tables as $t) {
        $tableId = "t_" . $t['id'];
        $cols = $tableColumns[$t['id']] ?? [];

        // Yükseklik Hesapla
        $currentTableHeight = 30 + (count($cols) * 26);
        if ($currentTableHeight > $rowHeight) $rowHeight = $currentTableHeight;

        // TABLO KUTUSU
        $tblNode = $root->addChild('mxCell');
        $tblNode->addAttribute('id', $tableId);
        $tblNode->addAttribute('value', strtoupper($t['table_name']));
        $tblNode->addAttribute('style', $swimlaneStyle);
        $tblNode->addAttribute('parent', '1');
        $tblNode->addAttribute('vertex', '1');

        $geometry = $tblNode->addChild('mxGeometry');
        $geometry->addAttribute('x', $xPos);
        $geometry->addAttribute('y', $yPos);
        $geometry->addAttribute('width', $tableWidth);
        $geometry->addAttribute('height', $currentTableHeight);
        $geometry->addAttribute('as', 'geometry');

        // SÜTUNLAR
        $yOffset = 30;
        $isEven = false; // Zebra stili için (Opsiyonel, şimdilik düz beyaz)

        foreach ($cols as $col) {
            $colId = "c_" . $col['id'];

            // İçerik Formatı: <b>PK</b> name <span style="color:gray">TYPE</span>
            // Draw.io HTML desteklediği için <span> kullanabiliriz

            $icon = "";
            $colName = $col['name'];
            $colType = '<span style="color:#94a3b8;font-size:10px;float:right;">' . strtoupper($col['data_type']) . '</span>';
            $decor = ""; // Bold, Underline vb.

            if ($col['is_primary_key']) {
                $icon = '<b style="color:' . $pkColor . ';margin-right:5px;">PK</b>';
                $decor = "font-weight:bold;";
            } elseif ($col['is_foreign_key']) {
                $icon = '<b style="color:' . $fkColor . ';margin-right:5px;">FK</b>';
                $decor = "color:#1e293b;";
            }

            // Rozetler (NN, UQ)
            $badges = "";
            if ($col['is_unique']) $badges .= '<span style="color:#7e22ce;font-size:9px;margin-left:5px;">UQ</span>';
            if (!$col['is_nullable'] && !$col['is_primary_key']) $badges .= '<span style="color:#dc2626;font-size:9px;margin-left:5px;">NN</span>';

            $val = $icon . '<span style="' . $decor . '">' . $colName . '</span>' . $badges . $colType;

            // Satır Arkaplanı (PK hafif sarı, FK hafif mavi)
            $rowFill = "none";
            if ($col['is_primary_key']) $rowFill = "#fffbeb";
            elseif ($col['is_foreign_key']) $rowFill = "#eff6ff";

            $currStyle = $colStyleBase . "fillColor=" . $rowFill . ";";

            $colNode = $root->addChild('mxCell');
            $colNode->addAttribute('id', $colId);
            $colNode->addAttribute('value', $val);
            $colNode->addAttribute('style', $currStyle);
            $colNode->addAttribute('parent', $tableId);
            $colNode->addAttribute('vertex', '1');

            $geoCol = $colNode->addChild('mxGeometry');
            $geoCol->addAttribute('y', $yOffset);
            $geoCol->addAttribute('width', $tableWidth);
            $geoCol->addAttribute('height', '26');
            $geoCol->addAttribute('as', 'geometry');

            $yOffset += 26;
        }

        // IZGARA HESABI
        $xPos += $tableWidth + 80; // Boşluk
        $colCounter++;

        if ($colCounter >= $maxColsPerRow) {
            $xPos = 40;
            $yPos += $rowHeight + 80;
            $colCounter = 0;
            $rowHeight = 0;
        }
    }

    // 5. İLİŞKİLER (Crow's Foot)
    foreach ($relationships as $rel) {
        $parentId = "t_" . $rel['parent_table_id'];
        $childId = "t_" . $rel['child_table_id'];

        if(!isset($tableColumns[$rel['parent_table_id']]) || !isset($tableColumns[$rel['child_table_id']])) continue;

        $relId = "rel_" . $rel['id'];

        // Draw.io Ok Stilleri
        // startArrow=ERone (1), endArrow=ERmany (N)
        $startArrow = "ERone";
        $endArrow = "ERmany";

        if ($rel['cardinality'] === '1:1') {
            $endArrow = "ERone";
        } elseif ($rel['cardinality'] === 'M:N') {
            $startArrow = "ERmany";
            $endArrow = "ERmany";
        }

        // Ortogonal Çizgi (Dik açılı)
        $edgeStyle = "edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;entryX=0;entryY=0.5;entryDx=0;entryDy=0;exitX=1;exitY=0.5;exitDx=0;exitDy=0;strokeColor=#64748b;strokeWidth=1;";
        $edgeStyle .= "startArrow=$startArrow;endArrow=$endArrow;";

        $edge = $root->addChild('mxCell');
        $edge->addAttribute('id', $relId);
        $edge->addAttribute('value', $rel['label'] ?? '');
        $edge->addAttribute('style', $edgeStyle);
        $edge->addAttribute('edge', '1');
        $edge->addAttribute('parent', '1');
        $edge->addAttribute('source', $parentId);
        $edge->addAttribute('target', $childId);

        $geoEdge = $edge->addChild('mxGeometry');
        $geoEdge->addAttribute('relative', '1');
        $geoEdge->addAttribute('as', 'geometry');
    }

    // ÇIKTI
    $filename = "ER_Diagram_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $projectName) . ".drawio";
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo $xml->asXML();
    exit;

} catch (Exception $e) {
    die("Export Error: " . $e->getMessage());
}
?>