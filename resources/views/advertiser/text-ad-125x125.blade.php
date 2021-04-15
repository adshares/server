<!DOCTYPE html>
<head>
    <meta content="utf-8">
    <title></title>
    <style>
        body {
            outline: none;
            cursor: pointer;
            width: {{ $width }}px;
            height: {{ $height }}px;
            margin: 0 auto;
        }

        .wrapper {
            width: {{ $width }}px;
            height: {{ $height }}px;
            overflow: hidden;
        }

        .ess-cell-ellipsis {
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }

        ._nghost-zfn-50 {
            align-items: center;
            display: flex;
            max-width: 100%;
            padding: 8px;
        }

        ._nghost-zfn-51 {
            color: #545454;
            font-size: 14px;
            white-space: nowrap;
            width: 100%;
        }

        .main-panel {
            display: flex;
            flex-direction: column;
            max-width: 100%;
        }

        .ad-preview-cell-container {
            display: flex;
            overflow: hidden;
        }

        .headline, .visurl, .description {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .visurl {
            color: #006621;
        }

        .link {
            color: #1a0dab;
            display: -webkit-box;
            overflow: hidden;
            text-decoration: none;
            text-overflow: ellipsis;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            white-space: normal;
        }

        .link:hover {
            text-decoration: underline;
        }

        .description {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            white-space: normal;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="_nghost-zfn-50">
        <div class="main-panel">
            <div class="ad-preview-cell-container">
                <div class="ess-cell-ellipsis _nghost-zfn-51">
                    <div class="headline">
                        <span class="link">{{ $title }}</span>
                    </div>
                    <div class="visurl">&#8234;{{ $domain }}&#8236;</div>
                    <div class="description">{{ $text }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
