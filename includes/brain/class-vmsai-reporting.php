<?php
/**
 * Strategic Reporting Engine.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Synthesizes performance metrics into executive-level ROI reports.
 */
class VMSAI_Reporting {

	/**
	 * Generate a monthly ROI synthesis.
	 */
	public static function generate_roi() {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) return array( 'ok' => false, 'message' => 'No active campaign found.' );

		$pace = VMSAI_Analytics::pace( (int) $campaign['id'] );
		$counts = VMSAI_Analytics::queue_counts();
		$by_channel = VMSAI_Analytics::by_channel( (int) $campaign['id'] );
		$winners = VMSAI_Brain::top_performers( 3 );

		$system = 'You are a Chief Marketing Officer. You summarize social media performance for a business owner. You are data-driven, professional, and focus on ROI and growth opportunities.';

		$prompt = "CAMPAIGN PERFORMANCE DATA:\n"
			. "Campaign: {$campaign['name']}\n"
			. "Target Views: {$pace['target']}\n"
			. "Actual Views: {$pace['views']}\n"
			. "Total Engagements: {$pace['engagements']}\n"
			. "Total Clicks: {$pace['clicks']}\n"
			. "Progress: {$pace['progress']}%\n"
			. "TECHNICAL STATUS: {$counts['published']} published, {$counts['failed']} failed.\n\n"
			. "TOP PERFORMERS:\n" . implode("\n", $winners) . "\n\n"
			. "TASK: Write a 3-paragraph ROI Synthesis.\n"
			. "Para 1: Executive Summary (Are we winning? Mention technical reliability if failures are high).\n"
			. "Para 2: Pillar Analysis (What content type is working best?)\n"
			. "Para 3: Strategic Recommendation for next month.\n\n"
			. "Return ONLY the report text. No preamble.";

		$result = vmsai()->text_engine()->generate( $system, $prompt, array( 'temperature' => 0.5 ) );

		return array(
			'ok'      => true,
			'stats'   => $pace,
			'report'  => $result['ok'] ? $result['text'] : 'Analysis complete. AI report generation failed.',
			'winners' => $winners,
		);
	}
}
