(function() {
	function addItem(table, fieldName) {
    const tbody = table.children[0];

		const index = tbody.children.length;
		const row = document.createElement("tr");

    const escapedName = fieldName.replace(/\[/g, "\\[").replace(/\]/g, "\\]");
    const nameRegexp = new RegExp(`\\[${escapedName}\\]\\[\\d+\\]`, "g");

    const idName = fieldName.replace(/\]\[/g, '_');
    const idRegexp = new RegExp(idName + "_\\d+", "g");

    row.innerHTML = tbody.children[0].innerHTML
      .replace(nameRegexp, `[${fieldName}][${index}]`)
      .replace(idRegexp, idName + `_${index}`)
      .replace(/selected=""/g, "");

    for (const input of row.querySelectorAll("input")) {
      input.value = "";
    }

    for (const multiSelect of row.querySelectorAll("select[multiple]")) {
      multiSelect.value = multiSelect.children[0].value;
    }

    for (const listTable of row.querySelectorAll("table.is-list")) {
      const tbody = listTable.children[0];
      Array.from(tbody.children).forEach((row, i) => {
        if (i > 0) {
          row.parentElement.removeChild(row);
        }
      });
    }

		tbody.appendChild(row);

		const controls = row.querySelectorAll(".wpct-plugin-fieldset-control");
		for (const control of controls) {
      control.removeAttribute("data-bound");
			bindControl(control);
		}
	}

	function removeItem(table) {
		const tbody = table.children[0];
		const rows = tbody.children;
		if (rows.length > 1) {
			tbody.removeChild(rows[rows.length - 1]);
		}
	}

	function bindControl(control) {
		if (control.dataset.bound === "1") {
			return;
		}

    const tableId = control.id.replace(/--controls$/, "");
    const fieldName = tableId.replace(/^.*__/, "").replace(/_/g, '][');
		const buttons = control.querySelectorAll("button");
		buttons.forEach((btn) => {
      const table = document.getElementById(tableId);

			const callback = btn.dataset.action === "add" ? addItem : removeItem;
			btn.addEventListener("click", (ev) => {
				ev.preventDefault();
				callback(table, fieldName);
			});
		});

		control.dataset.bound = "1";
	}

	const controls = document.querySelectorAll(".wpct-plugin-fieldset-control");
	for (const control of controls) {
		bindControl(control);
	}
})();
