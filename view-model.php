<?php
require_once 'connect.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. แก้ SQL ให้ดึง audio_file มาด้วย
    $sql = "SELECT g.title_ganesha, m.file_path, m.audio_file 
            FROM ganesha_info g 
            LEFT JOIN ar_media m ON g.info_id = m.info_id 
            WHERE g.info_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();

        $title = htmlspecialchars($data['title_ganesha']);

        // 2. ตรวจสอบไฟล์โมเดล (ถ้าไม่มีใน DB ให้ใช้ตัว Default)
        if (!empty($data['file_path'])) {
            $model_src = htmlspecialchars($data['file_path']);
        } else {
            $model_src = "model/AR_MOVE4.glb";
        }

        // 3. แก้ไขตรงนี้! ดึงเสียงจากฐานข้อมูล (ห้ามระบุเป็น audio/demovoice.mp3 ตายตัว)
        if (!empty($data['audio_file'])) {
            $audio_src = htmlspecialchars($data['audio_file']);
        } else {
            $audio_src = "audio/default_mantra.mp3"; // เสียงกลางกรณีไม่มีข้อมูล
        }
    } else {
        echo "<script>alert('ไม่พบข้อมูลปางพระพิฆเนศนี้'); window.location.href='index.php';</script>";
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Viewer - <?= $title ?></title>

    <meta property="og:title" content="ดูโมเดล 3D - <?= $title ?>" />
    <meta property="og:description" content="ร่วมชมบารมีองค์พระพิฆเนศแบบ 360 องศา" />
    <meta property="og:image" content="image/picganesha1.jpg" />

    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
    <style>
        body {
            margin: 0;
            background-color: #000;
            color: white;
            font-family: sans-serif;
            overflow: hidden;
        }

        .container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .aura-wrapper {
            position: relative;
            width: 100%;
            height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }



        model-viewer {
            width: 100%;
            height: 100%;
            z-index: 1;
            --progress-bar-color: #d4af37;
            --progress-bar-height: 5px;
        }

        .progress-bar {
            display: block;
            width: 33%;
            height: 10%;
            max-height: 2%;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            border-radius: 25px;
            box-shadow: 0px 3px 10px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.5s ease;
        }

        .update-bar {
            background-color: #d4af37;
            width: 0%;
            height: 100%;
            border-radius: 25px;
            transition: width 0.3s;
        }

        #status-text {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 14px;
            color: #d4af37;
        }

        .back-btn {
            margin-top: 20px;
            padding: 10px 30px;
            background: #d4af37;
            color: #000;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #f1c40f;
            transform: scale(1.05);
        }

        .audio-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            border: 1px solid #d4af37;
            border-radius: 50px;
            cursor: pointer;
            font-weight: bold;
            z-index: 10;
            transition: 0.3s;
            font-family: inherit;
        }

        .audio-btn:hover {
            background: #d4af37;
            color: #000;
        }

        @media (max-width: 1024px) {
            .aura-wrapper {
                height: 64vh;
            }

            .progress-bar {
                width: 46%;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 12px;
                justify-content: space-between;
            }

            .aura-wrapper {
                height: 54vh;
            }

            .audio-btn {
                top: 12px;
                right: 12px;
                padding: 8px 14px;
                font-size: 0.85rem;
            }

            .progress-bar {
                width: 66%;
            }

            #status-text {
                font-size: 12px;
            }

            .back-btn {
                margin-top: 10px;
                padding: 9px 20px;
                font-size: 0.92rem;
            }
        }

        @media (max-width: 480px) {
            .aura-wrapper {
                height: 50vh;
            }

            .progress-bar {
                width: 82%;
            }

            #status-text {
                top: -21px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <button id="audio-btn" class="audio-btn">🔇 ปิดเสียงอยู่</button>

        <audio id="bg-music" loop>
            <source src="<?= $audio_src ?>" type="audio/mpeg">
        </audio>

        <h2 style="z-index: 10; text-align: center; padding: 0 20px;">หมุนดู <?= $title ?> 360°</h2>

        <p style="text-align: center; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-top: -15px; margin-bottom: 20px; z-index: 10; position: relative;">
            ใช้นิ้วหรือเมาส์เพื่อหมุน และซูมเข้า-ออก
        </p>

        <div class="aura-wrapper">
            <div class="aura-bg"></div>

            <model-viewer
                <model-viewer
                src="<?= $model_src ?>"
                alt="3D <?= $title ?> Model"
                camera-controls
                auto-rotate

                autoplay
                animation-name="*"

                shadow-intensity="1"
                loading="eager"
                camera-orbit="0deg 75deg auto"
                background-color="transparent"
                environment-image="neutral"
                exposure="1.2">

                <div class="progress-bar" slot="progress-bar">
                    <div id="status-text">กำลังโหลด...</div>
                    <div class="update-bar"></div>
                </div>
            </model-viewer>
        </div>

        <a href="index.php" class="back-btn" style="z-index: 10;">กลับหน้าหลัก</a>
    </div>

    <script>
        const modelViewer = document.querySelector('model-viewer');
        const statusText = document.querySelector('#status-text');
        const updateBar = document.querySelector('.update-bar');
        const progressBar = document.querySelector('.progress-bar');

        modelViewer.addEventListener('progress', (event) => {
            const percent = Math.round(event.detail.totalProgress * 100);
            updateBar.style.width = `${percent}%`;
            statusText.textContent = `กำลังโหลด... ${percent}%`;
            if (percent === 100) {
                statusText.textContent = `โหลดเสร็จสิ้น`;
                setTimeout(() => {
                    progressBar.style.opacity = '0';
                    setTimeout(() => {
                        progressBar.style.display = 'none';
                    }, 500);
                }, 800);
            }
        });

        const audio = document.getElementById('bg-music');
        const audioBtn = document.getElementById('audio-btn');
        let isPlaying = false;
        audioBtn.addEventListener('click', () => {
            if (isPlaying) {
                audio.pause();
                audioBtn.textContent = '🔇 ปิดเสียงอยู่';
            } else {
                audio.play();
                audioBtn.textContent = '🔊 เปิดเสียงแล้ว';
            }
            isPlaying = !isPlaying;
        });
    </script>
</body>

</html>