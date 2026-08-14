<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Games Competition Entry Form - {{ $gameName ?? 'Sports' }} {{ $academicYear ?? '2026-27' }}</title>
<style>
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }
    * {
        box-sizing: border-box;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    body {
        font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
        color: #000;
        margin: 0;
        padding: 0;
        font-size: 10.5px;
        background: #fff;
    }

    .form-container {
        width: 100%;
        margin: 0 auto;
    }

    .form-header {
        text-align: center;
        margin-bottom: 10px;
        position: relative;
    }
    .logo-img {
        max-height: 60px;
        object-fit: contain;
        margin: 0 auto 4px;
        display: block;
    }

    .org-title {
        font-size: 17px;
        font-weight: bold;
        text-transform: uppercase;
        color: #000;
        margin: 2px 0 2px;
    }
    .org-subtitle {
        font-size: 9.5px;
        font-weight: bold;
        color: #000;
        margin-bottom: 6px;
    }
    .main-heading {
        font-size: 15px;
        font-weight: bold;
        text-transform: uppercase;
        margin: 6px 0 10px;
        text-decoration: underline;
        color: #000;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 7px;
    }
    .info-table td {
        padding: 2.5px 0;
        vertical-align: bottom;
    }
    .info-lbl {
        font-weight: bold;
        white-space: nowrap;
        font-size: 10.5px;
        color: #000;
    }
    .dots-underline {
        border-bottom: 1px dotted #000;
        padding-left: 6px;
        font-weight: bold;
        font-size: 10.5px;
        color: #000;
    }

    .checkbox-box {
        display: inline-block;
        width: 18px;
        height: 14px;
        border: 1px solid #000;
        text-align: center;
        line-height: 12px;
        font-weight: bold;
        font-size: 10.5px;
        margin-left: 4px;
        background: #fff;
    }

    .participants-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        table-layout: fixed;
    }
    .participants-table th,
    .participants-table td {
        border: 1px solid #000;
        padding: 4px 3px;
        text-align: center;
        vertical-align: middle;
        font-size: 9.5px;
        word-wrap: break-word;
    }
    .participants-table th {
        font-weight: bold;
        background-color: #f1f5f9;
        font-size: 9.5px;
        text-transform: uppercase;
        color: #000;
    }

    .col-sl { width: 4%; }
    .col-name { width: 22%; text-align: left; text-transform: uppercase; font-weight: bold; }
    .col-class { width: 6%; }
    .col-udise { width: 15%; }
    .col-dob { width: 11%; }
    .col-father { width: 14%; text-align: left; text-transform: uppercase; }
    .col-mother { width: 14%; text-align: left; text-transform: uppercase; }
    .col-photo { width: 14%; }

    .photo-box {
        width: 28mm;
        height: 36mm;
        border: 1px dashed #666;
        display: block;
        margin: 0 auto;
        text-align: center;
        overflow: hidden;
        background: #fafafa;
    }
    .photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .photo-text {
        padding-top: 35px;
        font-size: 8px;
        color: #555;
    }

    .footer-table {
        width: 100%;
        margin-top: 35px;
        border-collapse: collapse;
    }
    .footer-table td {
        text-align: center;
        vertical-align: bottom;
        font-weight: bold;
        font-size: 10.5px;
        width: 33.33%;
        color: #000;
    }

    @media print {
        body { background: #fff; }
    }
</style>
</head>
<body>

<div class="form-container">
    
    <!-- Header -->
    <div class="form-header">
        @if(!empty($sahodayaLogoUrl))
            <img src="{{ $sahodayaLogoUrl }}" class="logo-img" alt="Sahodaya Logo">
        @endif
        <div class="org-title">{{ $sahodayaName ?? 'MALAPPURAM CENTRAL SAHODAYA' }}</div>
        <div class="org-subtitle">{{ $sahodayaSubtitle ?? '(A Movement initiated and Guided by Central Board of Secondary Education, Delhi)' }}</div>
        <div class="main-heading">GAMES COMPETITION ENTRY FORM {{ $academicYear ?? '2026-27' }}</div>
    </div>

    <!-- Info Section -->
    <table class="info-table">
        <tr>
            <td class="info-lbl" style="width: 205px;">Name of the School with Address :&nbsp;</td>
            <td class="dots-underline">&nbsp;{{ $schoolName ?? ($schoolAddress ? $schoolName . ', ' . $schoolAddress : '') }}</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-lbl" style="width: 250px;">Team Manager's Name and Contact No. :&nbsp;</td>
            <td class="dots-underline">&nbsp;{{ $teamManager ?? '' }}</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-lbl" style="width: 130px;">Name of the Game :&nbsp;</td>
            <td class="dots-underline" style="width: 165px;">&nbsp;{{ $gameName ?? '' }}</td>
            <td class="info-lbl" style="width: 75px; text-align: right; padding-right: 4px;">Category :&nbsp;</td>
            <td class="dots-underline" style="width: 105px;">&nbsp;{{ $category ?? '' }}</td>
            <td style="text-align: right; font-weight: bold; width: 140px;">
                @php
                    $rawG = strtolower($gender ?? '');
                    $isBoysOnly = in_array($rawG, ['boys', 'boy', 'male', 'm']);
                    $isGirlsOnly = in_array($rawG, ['girls', 'girl', 'female', 'f']);
                    $checkBoys = !$isGirlsOnly;
                    $checkGirls = !$isBoysOnly;
                @endphp
                Boys <span class="checkbox-box">{{ $checkBoys ? '✓' : '' }}</span>
                &nbsp;&nbsp;
                Girls <span class="checkbox-box">{{ $checkGirls ? '✓' : '' }}</span>
            </td>
        </tr>
    </table>

    <!-- Region Display -->
    <table class="info-table">
        <tr>
            <td class="info-lbl" style="width: 65px;">Region :&nbsp;</td>
            <td class="dots-underline">&nbsp;{{ $regionName ?? 'District' }}</td>
        </tr>
    </table>

    <!-- Participants Table -->
    <table class="participants-table">
        <thead>
            <tr>
                <th class="col-sl">Sl<br>No</th>
                <th class="col-name">Name of the Student</th>
                <th class="col-class">Class</th>
                <th class="col-udise">UDISE PEN NUMBER/Adm.No.</th>
                <th class="col-dob">Date of Birth</th>
                <th class="col-father">Father's Name</th>
                <th class="col-mother">Mother's Name</th>
                <th class="col-photo">Photographs attested<br><span style="font-size: 8px; font-weight: normal;">(Sign. & Seal Principal)</span></th>
            </tr>
        </thead>
        <tbody>
            @php
                $studentList = $students ?? [];
                $minRows = max(4, count($studentList));
            @endphp
            @for ($i = 0; $i < $minRows; $i++)
                @php
                    $std = $studentList[$i] ?? null;
                @endphp
                <tr>
                    <td class="col-sl"><strong>{{ $i + 1 }}</strong></td>
                    <td class="col-name">{{ !empty($std['name']) ? mb_strtoupper($std['name'], 'UTF-8') : '' }}</td>
                    <td class="col-class">{{ $std['class'] ?? '' }}</td>
                    <td class="col-udise">{{ $std['udise_pen'] ?? ($std['admission_no'] ?? '') }}</td>
                    <td class="col-dob">{{ $std['dob'] ?? '' }}</td>
                    <td class="col-father">{{ !empty($std['father_name']) ? mb_strtoupper($std['father_name'], 'UTF-8') : '' }}</td>
                    <td class="col-mother">{{ !empty($std['mother_name']) ? mb_strtoupper($std['mother_name'], 'UTF-8') : '' }}</td>
                    <td class="col-photo">
                        <div class="photo-box">
                            @if(!empty($std['photo_url']))
                                <img src="{{ $std['photo_url'] }}" alt="Photo">
                            @else
                                <div class="photo-text">Affix Photo<br>(Attested)</div>
                            @endif
                        </div>
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- Footer Signatures -->
    <table class="footer-table">
        <tr>
            <td>
                <div style="height: 40px;"></div>
                Team manager
            </td>
            <td>
                <div style="height: 40px;"></div>
                School Seal
            </td>
            <td>
                <div style="height: 40px;"></div>
                Sign & Seal of Principal
            </td>
        </tr>
    </table>

</div>

</body>
</html>
