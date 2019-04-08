<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Labels</title>
</head>

<body>
    <?php
        // debug mode toggeling borders to view boxes
        $debug = true;
        
        // how many labels to print per page
        $labels_per_page = $settings->labels_per_page;

        // size of one PDF page in inches
        $page_width= $settings->labels_pagewidth;
        $page_height= $settings->labels_pageheight;

        // margin between paper edge and label in inches
        $page_margin_left = $settings->labels_pmargin_left;
        $page_margin_right = $settings->labels_pmargin_right;
        $page_margin_top = $settings->labels_pmargin_top;
        $page_margin_bottom = $settings->labels_pmargin_bottom;

        // width/ height of label in inches, 
        $label_width = $settings->labels_width;
        $label_height = $settings->labels_height;
        // 1 per page implies: 
        // page_width = labels_width + pmargin_left + pmargin_right
        // page_height = labels_hight + pmargin_top + pmargin_bottom
        $label_width = ($labels_per_page == 1 ? 
                            $page_width - $page_margin_left - $page_margin_right: $label_width);
        $label_height =($labels_per_page == 1 ? 
                            $page_height - $page_margin_top - $page_margin_bottom: $label_height);

        // space b(elow) and to the s(ide) of labels in inches
        // only relevant if labels_per_page > 1
        $bgutter = $settings->labels_display_bgutter;
        $sgutter = $settings->labels_display_sgutter;

        // max. front size within label in pt
        $fontsize = $settings->labels_fontsize;
        
        // text set by admin barcode settings (QR Code Text)
        $label_title = $settings->qr_text;
        
        // leave space on bottom for 1D barcode if necessary
        // value between 0..1 in terms of parts of label width
        $qr_size = ($settings->alt_barcode_enabled=='1') && ($settings->alt_barcode!='') 
                        ? 0.45 : 0.9;

        // Leave space on left for QR code if necessary
        // value between 0..1 in terms of parts of label width
        $qr_txt_size = ($settings->qr_code=='1' 
                            ? 1 - $qr_size - 0.05 : 0.9);
    ?>
    <style>
        @page {
            sheet-size: {{ $page_width }}in {{ $page_height }}in;
            margin-top: {{ $page_margin_top }}in;
            margin-left: {{ $page_margin_left }}in;
            margin-right: {{ $page_margin_right }}in;
            margin-bottom: {{ $page_margin_bottom }}in;
        }
        .label {
            position: relative;
            left: 0in; 
            width: {{ $label_width }}in; 
            height: {{ $label_height }}in;
            font-size: {{ $fontsize }}pt;
            font-family: arial, helvetica, sans-serif;
        }

        .qr_img {
            display: inline-block; 
            position: absolute; 
            left: 0px; 
            height: {{ $label_height *0.2 }}in;
        }

        .textfield {
            display: inline-block; 
            position: absolute; 
            right:0px;
            max-width: 45%;
            height: {{ $label_height *0.2 }}in;
            word-break: break-all;
            overflow: hidden;
        }

        .qr_text {
            font-weight: bold;
            max-height: 2em;
            overflow: hidden;
        }

        .asset_property {
            max-height: 2em;
            overflow: hidden;
        }

        .barcode_img {
            position: absolute; 
            top: calc({{ $label_height}}in - 1em - 3em);
            width: {{ $label_width}}in;
            max-heigth: 3em;
            clip: rect(0px, {{ $label_width}}in, 3em, 0px);
        }

        .asset_tag {
            position: absolute; 
            top: calc({{ $label_height}}in - 1em);
            width: {{ $label_width}}in;
            text-align: center;
            font-size: {{ $fontsize + 2 }}pt;
            font-family: arial, helvetica, sans-serif;
        }

        <!-- debug mode toggeling borders to view boxes -->
        @if ($debug)
            .label * { 
                border: 1px solid #900;
            }
        @endif

        @if ($snipeSettings->custom_css)
            {!! $snipeSettings->show_custom_css() !!}
        @endif
    </style>

    @foreach ($assets as $asset)
        <div class="label">
            <img src="/qr/{{ $asset->id }}/qr_code" class="qr_img">

            <div class="textfield">
                @if ($settings->qr_text!='')
                    <div class="qr_text">{{ $settings->qr_text }}</div>
                @endif

                @if (($settings->labels_display_company_name=='1') && ($asset->company))
                    <div class="asset_property">
                        C: {{ $asset->company->name }} </br>
                    </div>
                @endif

                @if (($settings->labels_display_model=='1') && ($asset->model->name!=''))
                    <div div class="asset_property">
                        {{ $asset->model->manufacturer->name }} {{ $asset->model->name }} </br>
                    </div>
                @endif

                @if (($settings->labels_display_name=='1') && ($asset->name!=''))
                    <div div class="asset_property">
                        N: {{ $asset->name }} </br>
                    </div>
                @endif

                @if (($settings->labels_display_serial=='1') && ($asset->serial!=''))
                    <div>
                        SN: {{ $asset->serial }} </br>
                    </div>
                @endif
            </div>

            @if ((($settings->alt_barcode_enabled=='1') && $settings->alt_barcode!=''))
                <img src="/qr/{{ $asset->id }}/barcode" class="barcode_img">
            @endif

            @if (($settings->labels_display_tag=='1') && ($asset->asset_tag!=''))
                <div class="asset_tag">
                    {{ $asset->asset_tag }} </br>
                </div>
            @endif
        </div>

        @if ($loop->remaining > 0)
            <div class="page-break"></div>
        @endif
   @endforeach

</body>
</html>