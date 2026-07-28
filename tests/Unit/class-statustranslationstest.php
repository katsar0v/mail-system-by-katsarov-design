<?php
/**
 * Status Translation Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

/**
 * Class StatusTranslationsTest
 *
 * The status vocabulary ("Pending", "Sent", "Cancelled", ...) is reused for
 * single-item badges and for filter links that count many items. Bulgarian and
 * German inflect those differently, so the badges use context-qualified strings
 * via _x(). These tests make sure every context used in the code is actually
 * translated in each locale, and pin down the Bulgarian badge wording.
 */
class StatusTranslationsTest extends TestCase {

	/**
	 * Directories scanned for _x() calls.
	 *
	 * @var string[]
	 */
	private const SOURCE_DIRS = array( 'admin', 'includes', 'public' );

	/**
	 * Translation catalogues that must cover every context.
	 *
	 * @var string[]
	 */
	private const CATALOGUES = array(
		'languages/mail-system.pot',
		'languages/mail-system-bg_BG.po',
		'languages/mail-system-de_DE.po',
	);

	/**
	 * Every context-qualified string in the code exists in the POT and both PO files.
	 */
	public function test_every_context_qualified_string_is_present_in_catalogues(): void {
		$used = $this->collect_context_calls();

		$this->assertNotEmpty( $used, 'No _x() calls were found — the scanner is broken.' );

		foreach ( self::CATALOGUES as $catalogue ) {
			$entries = $this->parse_catalogue( $catalogue );

			foreach ( $used as $key => $location ) {
				$this->assertArrayHasKey(
					$key,
					$entries,
					sprintf( '%s is missing the entry for %s (used in %s).', $catalogue, $key, $location )
				);
			}
		}
	}

	/**
	 * Translated locales leave no context-qualified string empty.
	 */
	public function test_translated_locales_have_no_empty_context_strings(): void {
		$used = $this->collect_context_calls();

		foreach ( array( 'languages/mail-system-bg_BG.po', 'languages/mail-system-de_DE.po' ) as $catalogue ) {
			$entries = $this->parse_catalogue( $catalogue );

			foreach ( array_keys( $used ) as $key ) {
				$this->assertNotSame(
					'',
					$entries[ $key ] ?? '',
					sprintf( '%s has no translation for %s.', $catalogue, $key )
				);
			}
		}
	}

	/**
	 * Bulgarian campaign badges agree with "кампания" (feminine singular).
	 */
	public function test_bulgarian_campaign_statuses_are_feminine_singular(): void {
		$entries = $this->parse_catalogue( 'languages/mail-system-bg_BG.po' );

		$this->assertSame( 'Чакаща', $entries['campaign status' . "\x04" . 'Pending'] );
		$this->assertSame( 'Завършена', $entries['campaign status' . "\x04" . 'Completed'] );
		$this->assertSame( 'Отменена', $entries['campaign status' . "\x04" . 'Cancelled'] );
		$this->assertSame( 'Насрочена', $entries['campaign status' . "\x04" . 'Scheduled'] );
	}

	/**
	 * Bulgarian email badges agree with "имейл" (masculine singular).
	 */
	public function test_bulgarian_email_statuses_are_masculine_singular(): void {
		$entries = $this->parse_catalogue( 'languages/mail-system-bg_BG.po' );

		$this->assertSame( 'Чакащ', $entries['email status' . "\x04" . 'Pending'] );
		$this->assertSame( 'Изпратен', $entries['email status' . "\x04" . 'Sent'] );
		$this->assertSame( 'Неуспешен', $entries['email status' . "\x04" . 'Failed'] );
		$this->assertSame( 'Отменен', $entries['email status' . "\x04" . 'Cancelled'] );
	}

	/**
	 * Bulgarian filter links stay plural, since they label a count of items.
	 */
	public function test_bulgarian_uncontextualised_statuses_are_plural(): void {
		$entries = $this->parse_catalogue( 'languages/mail-system-bg_BG.po' );

		$this->assertSame( 'Чакащи', $entries['Pending'] );
		$this->assertSame( 'Изпратени', $entries['Sent'] );
		$this->assertSame( 'Неуспешни', $entries['Failed'] );
		$this->assertSame( 'Отменени', $entries['Cancelled'] );
		$this->assertSame( 'Завършени', $entries['Completed'] );
		$this->assertSame( 'Насрочени', $entries['Scheduled'] );
		$this->assertSame( 'Активни', $entries['Active'] );
		$this->assertSame( 'Отписани', $entries['Unsubscribed'] );
	}

	/**
	 * Collect every _x()/esc_html_x()/esc_attr_x() call in the plugin sources.
	 *
	 * @return array<string, string> Map of "context\x04msgid" to the file it was found in.
	 */
	private function collect_context_calls(): array {
		$found = array();

		foreach ( self::SOURCE_DIRS as $dir ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( MSKD_PLUGIN_DIR . $dir )
			);

			foreach ( $iterator as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}

				$contents = file_get_contents( $file->getPathname() );
				$matches  = array();

				preg_match_all(
					"/(?:esc_html_x|esc_attr_x|_x)\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'mail-system'\s*\)/",
					$contents,
					$matches,
					PREG_SET_ORDER
				);

				foreach ( $matches as $match ) {
					$found[ $match[2] . "\x04" . $match[1] ] = $dir . '/' . $file->getFilename();
				}
			}
		}

		return $found;
	}

	/**
	 * Parse a PO/POT file into a map of message keys to translations.
	 *
	 * Keys are the msgid, prefixed with "context\x04" when the entry carries a msgctxt.
	 *
	 * @param string $relative_path Catalogue path relative to the plugin root.
	 * @return array<string, string> Map of message keys to translations.
	 */
	private function parse_catalogue( string $relative_path ): array {
		$lines   = file( MSKD_PLUGIN_DIR . $relative_path, FILE_IGNORE_NEW_LINES );
		$entries = array();
		$context = null;
		$msgid   = null;

		foreach ( $lines as $line ) {
			if ( 1 === preg_match( '/^msgctxt "(.*)"$/', $line, $match ) ) {
				$context = stripcslashes( $match[1] );
			} elseif ( 1 === preg_match( '/^msgid "(.*)"$/', $line, $match ) ) {
				$msgid = stripcslashes( $match[1] );
			} elseif ( 1 === preg_match( '/^msgstr "(.*)"$/', $line, $match ) && null !== $msgid && '' !== $msgid ) {
				$key             = null === $context ? $msgid : $context . "\x04" . $msgid;
				$entries[ $key ] = stripcslashes( $match[1] );
				$context         = null;
				$msgid           = null;
			}
		}

		return $entries;
	}
}
