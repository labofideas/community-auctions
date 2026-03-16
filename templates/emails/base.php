<?php
/**
 * Base email template.
 *
 * @package CommunityAuctions
 *
 * Available variables:
 * @var string $content      Inner content from specific template.
 * @var string $site_name    Site name.
 * @var string $site_url     Site URL.
 * @var string $header_image Header image URL.
 * @var string $footer_text  Footer text.
 * @var string $current_year Current year.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $site_name ); ?></title>
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			font-size: 16px;
			line-height: 1.6;
			color: #333333;
			background-color: #f6f1e9;
		}
		.email-wrapper {
			max-width: 600px;
			margin: 0 auto;
			padding: 20px;
		}
		.email-container {
			background-color: #ffffff;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
			overflow: hidden;
		}
		.email-header {
			background: linear-gradient(135deg, #1b3b33, #2a5548);
			padding: 30px 40px;
			text-align: center;
		}
		.email-header img {
			max-width: 200px;
			height: auto;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 600;
			color: #ffffff;
		}
		.email-body {
			padding: 40px;
		}
		.email-content p {
			margin: 0 0 16px;
		}
		.email-content h2 {
			margin: 0 0 20px;
			font-size: 22px;
			color: #1b3b33;
		}
		.btn {
			display: inline-block;
			padding: 14px 28px;
			background-color: #c65a1e;
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			font-size: 16px;
			margin: 20px 0;
		}
		.btn:hover {
			background-color: #b05118;
		}
		.btn-secondary {
			background-color: #1b3b33;
		}
		.btn-secondary:hover {
			background-color: #2a5548;
		}
		.highlight-box {
			background-color: #f6f1e9;
			border-left: 4px solid #c65a1e;
			padding: 20px;
			margin: 20px 0;
			border-radius: 4px;
		}
		.highlight-box .label {
			font-size: 14px;
			color: #666666;
			margin-bottom: 4px;
		}
		.highlight-box .value {
			font-size: 24px;
			font-weight: 700;
			color: #c65a1e;
		}
		.info-table {
			width: 100%;
			border-collapse: collapse;
			margin: 20px 0;
		}
		.info-table td {
			padding: 12px 0;
			border-bottom: 1px solid #eeeeee;
		}
		.info-table td:first-child {
			color: #666666;
			font-weight: 500;
		}
		.info-table td:last-child {
			text-align: right;
			font-weight: 600;
		}
		.email-footer {
			background-color: #f8f8f8;
			padding: 20px 40px;
			text-align: center;
			font-size: 14px;
			color: #888888;
		}
		.email-footer a {
			color: #1b3b33;
			text-decoration: none;
		}
		.social-links {
			margin: 16px 0;
		}
		.social-links a {
			display: inline-block;
			margin: 0 8px;
		}
		@media only screen and (max-width: 600px) {
			.email-wrapper {
				padding: 10px;
			}
			.email-header,
			.email-body,
			.email-footer {
				padding: 20px;
			}
			.highlight-box .value {
				font-size: 20px;
			}
		}
	</style>
</head>
<body>
	<div class="email-wrapper">
		<div class="email-container">
			<div class="email-header">
				<?php if ( ! empty( $header_image ) ) : ?>
					<img src="<?php echo esc_url( $header_image ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
				<?php else : ?>
					<h1><?php echo esc_html( $site_name ); ?></h1>
				<?php endif; ?>
			</div>
			<div class="email-body">
				<div class="email-content">
					<?php echo $content; ?>
				</div>
			</div>
			<div class="email-footer">
				<p><?php echo wp_kses_post( $footer_text ); ?></p>
				<p>
					<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
				</p>
				<p>&copy; <?php echo esc_html( $current_year ); ?></p>
			</div>
		</div>
	</div>
</body>
</html>
