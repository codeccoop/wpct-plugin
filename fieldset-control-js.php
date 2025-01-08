<?php
/**
 * Fieldset Control Script.
 *
 * @param Setting $setting - Instance of the setting object.
 * @param string $field_name - Name of the current field.
 * @param mixed $field_value - Value of the current field.
 * @param Settings $this - Instance of the settings store.
 */

if (!defined('ABSPATH')) {
	exit();
}

$is_array = is_array($field_value);
$table_id = $setting->full_name() . '__' . str_replace('][', '_', $field_name);
?>

(function() {
	function renderRowContent(index) {
		<?php if ($is_array) : ?>
			return `<table id="<?php echo esc_attr($table_id . '_' . $index) ?>">
			<?php foreach (array_keys($field_value) as $key) : ?>
				<tr>
					<th><?php echo esc_html($key) ?></th>
					<td><?php echo $this->input_render($setting, implode('][', [$field_name, $index, $key]), $field_value[$key]); ?></td>
				</tr>
			<?php endforeach; ?>
			</table>`;
		<?php else : ?>
			return `<?php echo $this->input_render($setting, implode('][', [$field_name, $index]), $field_value); ?>`;
		<?php endif; ?>
	}

	function addItem(ev) {
		ev.preventDefault();
		const table = document.getElementById("<?php echo esc_attr($table_id) ?>")
			.children[0];
		const tr = document.createElement("tr");
		tr.innerHTML = "<td>" + renderRowContent(table.children.length) + "</td>";
		table.appendChild(tr);
	}

	function removeItem(ev) {
		ev.preventDefault();
		const table = document.getElementById("<?php echo esc_attr($table_id) ?>").children[0];
		const rows = table.children;
		table.removeChild(rows[rows.length - 1]);
	}

	const target = document.currentScript.dataset.control;
	const control = document.querySelector("." + target);
	const buttons = control.querySelectorAll("button");
	buttons.forEach((btn) => {
		const callback = btn.dataset.action === "add" ? addItem : removeItem;
		btn.addEventListener("click", callback);
	});
})();
