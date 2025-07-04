<?php
/**
 * Kompletny, samowystarczalny plik generatora grafik z unikalnymi klasami CSS.
 */

// --- Zabezpieczenie i ładowanie środowiska WordPress ---
// ABSPATH powinno być zdefiniowane, jeśli to jest wywoływane w kontekście admin-ajax.php.
// Jeśli jest to wywoływane bezpośrednio, to poniższy blok go załaduje.
if ( ! defined( 'ABSPATH' ) ) {
    $wp_load_path = realpath(__DIR__ . '/../../../../wp-load.php'); // Ścieżka do wp-load.php
    if ($wp_load_path) {
        require_once($wp_load_path);
    } else {
        exit('Nie można załadować środowiska WordPress.');
    }
}

// Upewnij się, że użytkownik ma uprawnienia (jeśli ten plik jest dostępny bezpośrednio)
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Brak uprawnień.' );
}

// Sprawdź, czy helper do stripslashes jest dostępny (powinien być załadowany przez DEvents_Core)
// Jeśli DEW_PLUGIN_URL nie jest zdefiniowane, próbujemy je zdefiniować.
if ( ! defined('DEW_PLUGIN_URL') ) {
    // Zakładając, że ten plik jest w 'admin/', DEW_PLUGIN_URL to 2 poziomy wyżej
    define( 'DEW_PLUGIN_URL', plugin_dir_url( dirname(__FILE__, 2) ) ); 
}
if ( ! function_exists('devents_unslash_event_data') ) {
    require_once DEW_PLUGIN_URL . 'includes/devents-helpers.php'; // Załaduj helper, jeśli nie jest już dostępny
}


// --- Pobieranie i przygotowanie danych wydarzenia ---
// Event ID może pochodzić z POST (AJAX modala) lub GET (bezpośrednie wejście)
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : (isset($_GET['event_id']) ? intval($_GET['event_id']) : 0);

if ( ! $event_id ) {
    echo '<p class="error-message">Błąd: Brak ID wydarzenia.</p>'; // Użyj message-box--error jeśli zdefiniowano
    return;
}

global $wpdb;
$event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}events_list WHERE id = %d", $event_id));

if ( ! $event ) {
    echo '<p class="error-message">Nie znaleziono wydarzenia.</p>'; // Użyj message-box--error jeśli zdefiniowano
    return;
}

// --- KLUCZOWA ZMIANA: ZASTOSOWANIE UNLASHINGU PRZEZ HELPERA ---
// Zastosuj stripslashes do obiektu event po pobraniu, dla wszystkich tekstowych pól
$event = devents_unslash_event_data($event);

// Przygotowanie zmiennych dla szablonu
$title = $event->title;
$category = $event->category;
$image_url = !empty($event->image_url) ? $event->image_url : DEW_PLUGIN_URL . 'assets/images/default-event-bg.jpg';
$start_timestamp = strtotime($event->start_datetime);
$date = date_i18n('d.m.Y', $start_timestamp);
$time = date_i18n('H:i', $start_timestamp);

// Organizator: Dane z $event->organizator są już unslashed przez helpera.
// Jeśli user_id jest ustawione, sprawdź również org_name z user_meta.
$organizer = $event->organizator; // Bierzemy wartość z pola 'organizator' z tabeli events_list
if (empty($organizer) && !empty($event->user_id)) { // Jeśli pole 'organizator' jest puste, ale wydarzenie ma user_id
    $organizer_from_user_meta = get_user_meta($event->user_id, 'org_name', true); // get_user_meta nie jest unslashed
    // Zastosuj stripslashes do danych z user_meta
    $organizer = $organizer_from_user_meta ? stripslashes($organizer_from_user_meta) : get_the_author_meta('display_name', $event->user_id);
    if (empty($organizer)) {
        $organizer = 'N/A'; // Domyślna wartość, jeśli nic nie znaleziono
    }
} elseif (empty($organizer) && empty($event->user_id)) {
    $organizer = 'N/A'; // Jeśli ani organizator w evencie, ani user_id nie są ustawione
}


$access = !empty($event->accessibility) ? array_map('trim', explode(',', $event->accessibility)) : [];
// Elementy w tablicy $access są już czyste, bo $event->accessibility było unslashed.

$is_online = in_array($event->delivery_mode, ['Online', 'Hybrydowy']);
$price_label = ((float)$event->price > 0) ? number_format_i18n($event->price, 2) . ' zł' : 'Bezpłatnie';
$price_class = ((float)$event->price > 0) ? 'paid' : 'free';
$logo_url = DEW_PLUGIN_URL . 'assets/images/logo-white.png';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<link rel="stylesheet" href="<?php echo esc_url(DEW_PLUGIN_URL . 'assets/css/admin/graphic-generator.css'); ?>">

<div style="text-align:center; margin-bottom:10px;">
  <div class="theme-toggle">
    <button id="light-btn" class="button">Jasny motyw</button>
    <button id="dark-btn" class="button active">Ciemny motyw</button>
  </div>
  <button id="download-btn" class="button button-primary">Pobierz grafikę</button>
</div>

<div class="event-graphic-preview-wrapper" style="width:100%; overflow:auto; text-align:center;">
  <div id="graphicPreview" class="img-event-graphic dark" style="display:inline-block; transform: scale(0.5); transform-origin: top center;">
    </div>
</div>

<div id="graphicFull" style="position:absolute; top:-9999px; left:-9999px;">
  <div class="img-event-graphic dark">
    <div class="img-background-image" style="background: center/cover url('<?php echo esc_url($image_url); ?>');"></div>
    <?php if ( $is_online ): ?><div class="img-transmission-badge">🔴 TRANSMISJA ONLINE</div><?php endif; ?>
    <div class="img-overlay-content">
      <div class="img-info-bar">
        <div class="img-category"><?php echo esc_html($category); ?></div>
        <div class="img-title"><?php echo esc_html($title); ?></div>
        <div class="img-meta-item"><span class="material-symbols-outlined">calendar_month</span><?php echo esc_html($date); ?></div>
        <div class="img-meta-item"><span class="material-symbols-outlined">schedule</span><?php echo esc_html($time); ?></div>
        <?php if ( ! empty($event->location) ): ?><div class="img-meta-item"><span class="material-symbols-outlined">map</span><?php echo esc_html($event->location); ?></div><?php endif; ?>
        <?php if ( $organizer ): ?><div class="img-meta-item"><span class="material-symbols-outlined">person</span><?php echo esc_html($organizer); ?></div><?php endif; ?>
        <?php if ( ! empty($access) ): ?><div class="img-accessibility-list"><?php foreach ($access as $a): ?><div class="img-accessibility-item"><?php echo esc_html($a); ?></div><?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
    <div class="img-footer-bar">
      <div class="img-price <?php echo esc_attr($price_class); ?>"><?php echo esc_html($price_label); ?></div>
      <div class="img-footer-logo"><img src="<?php echo esc_url($logo_url); ?>" alt="DEvents Logo"></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function(){
  const lightBtn = document.getElementById('light-btn');
  const darkBtn  = document.getElementById('dark-btn');
  const previewContainer = document.getElementById('graphicPreview');
  const fullGraphicContainer = document.getElementById('graphicFull');
  const fullGraphic = fullGraphicContainer.querySelector('.img-event-graphic');
  const downloadBtn = document.getElementById('download-btn');

  if (!lightBtn || !darkBtn || !previewContainer || !fullGraphic || !downloadBtn) return;
  
  // Kopiowanie zawartości do podglądu (aby html2canvas działał na ukrytym elemencie)
  previewContainer.innerHTML = fullGraphic.innerHTML;
  
  function setTheme(t) {
    [previewContainer, fullGraphic].forEach(e => {
      if (e) {
          e.classList.remove('light','dark');
          e.classList.add(t);
      }
    });
    lightBtn.classList.toggle('active', t==='light');
    darkBtn.classList.toggle('active', t==='dark');
  }

  lightBtn.addEventListener('click', ()=> setTheme('light'));
  darkBtn.addEventListener('click', ()=> setTheme('dark'));

  downloadBtn.addEventListener('click', async ()=>{
    downloadBtn.innerText = 'Generowanie...';
    downloadBtn.disabled = true;
    try {
      const canvas = await html2canvas(fullGraphic, { scale: 2, useCORS: true });
      const blob = await new Promise(resolve => canvas.toBlob(resolve,'image/png', 1));
      const filename = `wydarzenie-devents-<?php echo esc_js($event_id); ?>.png`;
      const file = new File([blob], filename, {type: blob.type});

      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try {
          await navigator.share({ files: [file], title: filename });
        } catch(e) {
          const link = document.createElement('a');
          link.href = URL.createObjectURL(blob);
          link.download = filename;
          link.click();
          URL.revokeObjectURL(link.href);
        }
      } else {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(()=>URL.revokeObjectURL(link.href),1000);
      }
    } catch(err) {
      console.error('Błąd generowania grafiki:', err);
      alert('Wystąpił błąd podczas generowania grafiki.');
    } finally {
      downloadBtn.innerText = 'Pobierz grafikę';
      downloadBtn.disabled = false;
    }
  });

  setTheme('dark');
})();
</script>