<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Labels</title>

</head>
<body>
    <?php
        #$settings->labels_width = $settings->labels_width - $settings->labels_display_sgutter;
        #$settings->labels_height = $settings->labels_height - $settings->labels_display_bgutter;
        // Leave space on bottom for 1D barcode if necessary
        $qr_size = ($settings->alt_barcode_enabled=='1') && ($settings->alt_barcode!='') ? $settings->labels_height * .4 : $settings->labels_height * .9;
        // Leave space on left for QR code if necessary
        $qr_txt_size = ($settings->qr_code=='1' ? $settings->labels_width - $qr_size - .2: $settings->labels_width);
    ?>
    <style>
        @page {
            sheet-size: {{ $settings->labels_pagewidth }}in {{ $settings->labels_pageheight }}in;
            size: auto;
            overflow: hidden;
            margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
        }

        .label {
            width: {{ $settings->labels_width }}in;
            height: {{ $settings->labels_height }}in;
            padding: 0in;
            margin-right: {{ $settings->labels_display_sgutter }}in; /* the gutter */
            margin-bottom: {{ $settings->labels_display_bgutter }}in;
            display: inline-block;
            overflow: hidden;
	        font-size: {{ $settings->labels_fontsize }}pt;
        }

  	.page-break  {
    		page-break-after:always;
  	}

        .label_qr {
            width: {{ $qr_size }}in;
            height: {{ $qr_size }}in;
            float: left;
            display: inline-block;
            padding-right: .04in;
        }

        img.qr_img {
            width: 100%;
            height: 100%;
        }

        .barcode {
            display: inline-block;
            margin-left: auto;
            margin-right: auto;
	    height: {{ $settings->labels_height * .3 }}in;
	    width: 100%;
        }

        .barcode_container {
            float: left;
            width: 100%;
            display: inline;
        }

	.label_title {
	    font-weight: bold;
	    width: {{ $qr_txt_size }}in;
            padding-top: .02in;
            font-family: arial, helvetica, sans-serif;
            padding-right: .01in;
            overflow: hidden !important;
            display: inline-block;
            word-wrap: break-word;
            word-break: break-all;
            text-align: right;
	}

        .qr_text {
            width: {{ $qr_txt_size }}in;
            height: {{ $qr_size }}in;
            padding-top: .02in;
            font-family: arial, helvetica, sans-serif;
            padding-right: .01in;
            overflow: hidden !important;
            display: inline-block;
            word-wrap: break-word;
            word-break: break-all;
	    text-align: right;
        }

        .next-padding {
            margin: {{ $settings->labels_pmargin_top }}in {{ $settings->labels_pmargin_right }}in {{ $settings->labels_pmargin_bottom }}in {{ $settings->labels_pmargin_left }}in;
        }

	.center {
	text-align: center;
	font-size: {{ $settings->labels_fontsize + 2 }}pt;
	font-family: arial, helvetica, sans-serif;
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
        <div>
            @if ($settings->qr_text!='')
            <div class="label_title">{{ $settings->qr_text }}</div>
            @endif
        
        	<div class="qr_text">
            @if (($settings->labels_display_company_name=='1') && ($asset->company))
            <div
                C: {{ $asset->company->name }} </br>
            </div>
            @endif

            @if (($settings->labels_display_model=='1') && ($asset->model->name!=''))
            <div>
                {{ $asset->model->manufacturer->name }} {{ $asset->model->name }} </br>
            </div>
            @endif

            @if (($settings->labels_display_name=='1') && ($asset->name!=''))
            <div>
                N: {{ $asset->name }} </br>
            </div>
            @endif

            @if (($settings->labels_display_tag=='1') && ($asset->asset_tag!=''))
            <div>
                {{ $asset->asset_tag }} </br>
            </div>
            @endif

            @if (($settings->labels_display_serial=='1') && ($asset->serial!=''))
            <div>
                SN: {{ $asset->serial }} </br>
            </div>
           	 @endif
        </div>
	</div>

        @if ((($settings->alt_barcode_enabled=='1') && $settings->alt_barcode!=''))
        <div class="barcode_container">
            <img src="/qr/{{ $asset->id }}/barcode" class="barcode">
            <div class="center">  {{ $asset->asset_tag }} </div>
        </div>
    @endif
    </div>

   @if ($loop->remaining > 0)
   <div class="page-break"></div>
   @endif

   @endforeach
</body>
</html>