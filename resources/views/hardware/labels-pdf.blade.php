<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Labels</title>

</head>
<body>
    <style>
        @page {
            sheet-size: 50.8mm 31.75mm;
            size: auto;
            overflow: hidden;
        }

        .label {
            clear: both;
            width: 50.8mm;
            height: 30mm;
            padding: 1mm 2mm 1mm 2mm;
            font-size: 8pt;
            page-break-after: always;
        }

        .label_text {
            max-height: 28mm;
        }

        .label_qr {
            width: 15mm;
            height: 15mm;
            float: left;
        }

        .label_qr .qr_img {
            width: 100%;
            height: 100%;
        }

        .barcode {
            display: block;
            margin-left: auto;
            margin-right: auto;
        } 

        .barcode_container {
            float: left;
            width: 100%;
            display: inline;
            height: 50px;
        } 

        .next-padding {
            margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
        }

        .label-model::before {
            content: "M: "
        }
        .label-company::before {
            content: "C: "
        }
        .label-asset_tag::before {
            content: "T: "
        }
        .label-serial::before {
            content: "S: "
        }
        .label-name::before {
            content: "N: "
        }

        @if ($snipeSettings->custom_css)
            {!! $snipeSettings->show_custom_css() !!}
        @endif
    </style>

    @foreach ($assets as $asset)
    <div class="label">
        <div class="label_qr">
            <img src="/qr/{{ $asset->id }}/qr_code" class="qr_img">
        </div>
        @if ($settings->qr_text!='')
        <div class="label_title">
            <strong>{{ $settings->qr_text }}</strong>
        </div>
        @endif
        <div class="label_text">
            @if (($settings->labels_display_company_name=='1') && ($asset->company))
                <span>C: {{ $asset->company->name }}</span><br />
            @endif
            @if (($settings->labels_display_name=='1') && ($asset->name!=''))
                <span>N: {{ $asset->name }}</span><br />
            @endif
            @if (($settings->labels_display_tag=='1') && ($asset->asset_tag!=''))
                 <span>T: {{ $asset->asset_tag }}</span><br />
            @endif
            @if (($settings->labels_display_serial=='1') && ($asset->serial!=''))
                <span>S: {{ $asset->serial }}</span><br />
            @endif
        </div>
        
        @if ((($settings->alt_barcode_enabled=='1') && $settings->alt_barcode!=''))
        <div class="label-alt_barcode barcode_container">
            <img src="/qr/{{ $asset->id }}/barcode" class="barcode">
        </div>
    @endif
    </div>
    @endforeach
</body>
</html>