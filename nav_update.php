<?php
$file = __DIR__ . '/partials/footer.php';
$content = file_get_contents($file);

$oldStyle = <<<CSS
  .nav-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    color: #94a3b8 !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    font-family: 'Nunito', sans-serif !important;
    gap: 4px !important;
    height: 58px !important;
    border: 2.5px solid transparent !important;
    background: transparent !important;
    position: relative;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    border-radius: 20px !important;
    margin: 0 3px !important;
  }
  .nav-item i {
    font-size: 24px !important;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    color: #94a3b8 !important;
  }

  /* Active state - 3D POP UP */
  .nav-item.active { 
    background: #fff8f0 !important; 
    border-color: #ea580c !important; 
    color: #ea580c !important; 
    box-shadow: 0 4px 0 #c2410c !important;
    transform: translateY(-4px) !important;
  }
  .nav-item.active i {
    color: #ea580c !important;
    transform: scale(1.1) !important;
  }

  /* Center PLAY button */
  .nav-item--play {
    position: relative !important;
    overflow: visible !important;
    height: auto !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    transform: none !important;
  }
  .nav-play-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    margin-top: -34px; /* lift above nav bar dome */
  }
  .nav-play-btn {
    width: 66px; height: 66px; /* BIGGER */
    background: linear-gradient(135deg, #f97316, #ea580c);
    border: 4px solid #fff;
    border-radius: 24px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 0 #c2410c, 0 10px 24px rgba(234,88,12,0.4);
    transition: transform 0.1s, box-shadow 0.1s;
    position: relative;
    z-index: 10;
  }
  .nav-play-btn i { font-size: 32px !important; color: #fff !important; transform: none !important; margin-left: 3px; }
  .nav-item--play:active .nav-play-btn { transform: translateY(5px); box-shadow: 0 1px 0 #c2410c; }
  .nav-item--play.active .nav-play-btn { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 0 #d97706; }
  .nav-play-label { font-size: 9px; font-weight: 900; color: #94a3b8; font-family: 'Nunito', sans-serif; }
  .nav-item--play.active .nav-play-label { color: #d97706; }
CSS;

$newStyle = <<<CSS
  .nav-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    color: #64748b !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    font-family: 'Nunito', sans-serif !important;
    gap: 4px !important;
    height: 56px !important;
    border: 2.5px solid #cbd5e1 !important;
    background: #f8fafc !important;
    box-shadow: 0 3px 0 #cbd5e1 !important;
    position: relative;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    border-radius: 16px !important;
    margin: 0 4px !important;
  }
  .nav-item:active {
    transform: translateY(3px) !important;
    box-shadow: none !important;
  }
  .nav-item i {
    font-size: 22px !important;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    color: #64748b !important;
  }

  /* Active state - ULTRA COMPACT 3D POP UP */
  .nav-item.active { 
    background: #fff !important; 
    border-color: #ea580c !important; 
    color: #c2410c !important; 
    box-shadow: 0 3px 0 #c2410c !important;
    transform: translateY(-4px) !important;
  }
  .nav-item.active i {
    color: #ea580c !important;
    transform: scale(1.1) !important;
  }

  /* Center PLAY button */
  .nav-item--play {
    position: relative !important;
    overflow: visible !important;
    height: auto !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    transform: none !important;
  }
  .nav-play-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    margin-top: -30px; /* lift above nav bar dome */
  }
  .nav-play-btn {
    width: 60px; height: 60px; /* BIGGER */
    background: linear-gradient(135deg, #f97316, #ea580c);
    border: 3px solid #fff;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 0 #c2410c, 0 8px 20px rgba(234,88,12,0.4);
    transition: transform 0.1s, box-shadow 0.1s;
    position: relative;
    z-index: 10;
  }
  .nav-play-btn i { font-size: 30px !important; color: #fff !important; transform: none !important; margin-left: 3px; }
  .nav-item--play:active .nav-play-btn { transform: translateY(4px); box-shadow: 0 0 0 #c2410c; }
  .nav-item--play.active .nav-play-btn { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 4px 0 #d97706; }
  .nav-play-label { font-size: 9px; font-weight: 900; color: #94a3b8; font-family: 'Nunito', sans-serif; }
  .nav-item--play.active .nav-play-label { color: #d97706; }
CSS;

$content = str_replace($oldStyle, $newStyle, $content);
file_put_contents($file, $content);
echo "Nav updated.";
