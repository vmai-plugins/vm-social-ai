<?php
/**
 * Labs — the creative workbenches, grouped under one tab.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="vmsai-nav-sub">
	<a href="#vmsai-section-image-lab" class="is-active"><?php esc_html_e( 'Image Lab', 'vm-social-ai-pro' ); ?></a>
	<a href="#vmsai-section-video-lab"><?php esc_html_e( 'Video Lab', 'vm-social-ai-pro' ); ?></a>
</div>

<div id="vmsai-section-image-lab" class="vmsai-subview">
	<?php include VMSAI_PATH . 'admin/views/image-lab.php'; ?>
</div>

<div id="vmsai-section-video-lab" class="vmsai-subview" style="display:none;">
	<?php include VMSAI_PATH . 'admin/views/video-lab.php'; ?>
</div>
