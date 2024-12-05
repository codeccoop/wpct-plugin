<?php
// Script to be buffered from settings class
$value = $data[$field][0];
$is_array = is_array($value);
$table_id = $setting_name . '__' . str_replace('][', '_', $field);
?>

<script>
    (function() {
        function renderRowContent(index) {
            <?php if ($is_array) : ?>
                return `<table id="<?= $table_id ?>_${index}">
          <?php foreach (array_keys($value) as $key) : ?>
            <tr>
                <th><?= $key ?></th>
                <td><?= $this->input_render($setting, $field . '][${index}][' . $key, $value[$key]); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>`;
            <?php else : ?>
                return `<?= $this->input_render($setting, $field . '][${index}', $value); ?>`;
            <?php endif; ?>
        }

        function addItem(ev) {
            ev.preventDefault();
            const table = document.getElementById("<?= $table_id ?>")
                .children[0];
            const tr = document.createElement("tr");
            tr.innerHTML =
                "<td>" + renderRowContent(table.children.length) + "</td>";
            table.appendChild(tr);
        }

        function removeItem(ev) {
            ev.preventDefault();
            const table = document.getElementById("<?= $table_id ?>")
                .children[0];
            const rows = table.children;
            table.removeChild(rows[rows.length - 1]);
        }

        const buttons = document.currentScript.previousElementSibling.querySelectorAll("button");
        buttons.forEach((btn) => {
            const callback = btn.dataset.action === "add" ? addItem : removeItem;
            btn.addEventListener("click", callback);
        });
    })();
</script>
