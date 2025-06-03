jQuery(document).ready(function ($) {
	const registrationForm = $(".registration-form"); // Target the specific form container

	// Ensure the form exists before trying to attach handlers
	if (registrationForm.length === 0) {
		console.warn(
			'Registration form container ".registration-form" not found.'
		);
		return;
	}

	// Define these variables in a scope accessible to both handlers
	const icoField = registrationForm.find('[name="ico"]');
	const companyNameField = registrationForm.find('[name="company-name"]');
	const addressField = registrationForm.find('[name="address"]');
	const statusSpan = registrationForm.find("#aresStatus");
	const loadAresDataButton = registrationForm.find("#loadAresData");

	// Store the IČO for which data was last successfully fetched
	let lastFetchedIco = null;

	loadAresDataButton.on("click", function () {
		const ico = icoField.val().trim();

		if (ico.length !== 8 || !/^\d{8}$/.test(ico)) {
			statusSpan.text("IČO musí mít 8 číslic.").css("color", "red");
			companyNameField.val("");
			addressField.val("");
			lastFetchedIco = null; // Reset last fetched IČO
			return;
		}

		statusSpan.text("Ověřuji IČO...").css("color", "orange");
		companyNameField.val("");
		addressField.val("");
		lastFetchedIco = null; // Reset last fetched IČO until success

		$.ajax({
			url:
				"https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/" +
				ico,
			method: "GET",
			dataType: "json",
			success: function (data) {
				if (data && data.obchodniJmeno) {
					companyNameField.val(data.obchodniJmeno);

					if (data.sidlo && data.sidlo.textovaAdresa) {
						addressField.val(data.sidlo.textovaAdresa);
					} else {
						addressField.val("");
					}
					statusSpan.text("Údaje načteny.").css("color", "green");
					lastFetchedIco = ico; // Store the successfully fetched IČO
				} else {
					statusSpan
						.text(
							"Společnost nenalezena nebo odpověď neobsahuje název."
						)
						.css("color", "red");
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				let errorMessage = "Chyba při komunikaci s ARES API.";
				if (jqXHR.status === 404) {
					errorMessage = "IČO nebylo nalezeno v ARES.";
				} else if (jqXHR.status === 400) {
					errorMessage = "Chybný formát IČO pro ARES (zkontrolujte).";
				} else if (jqXHR.status === 500 || jqXHR.status === 503) {
					errorMessage =
						"Služba ARES je dočasně nedostupná. Zkuste později.";
				}
				console.error(
					"ARES API Error:",
					jqXHR.status,
					textStatus,
					errorThrown,
					jqXHR.responseText
				);
				statusSpan.text(errorMessage).css("color", "red");
			},
		});
	});

	// Handle manual changes to the IČO field
	icoField.on("input", function () {
		const currentIco = $(this).val().trim();

		// If the current IČO is different from the last successfully fetched IČO,
		// and the company/address fields are filled (meaning they hold data from a previous fetch)
		if (
			lastFetchedIco &&
			currentIco !== lastFetchedIco &&
			(companyNameField.val() !== "" || addressField.val() !== "")
		) {
			statusSpan
				.text(
					'IČO bylo změněno. Klikněte na "Načíst údaje" pro aktualizaci.'
				)
				.css("color", "orange");
			// Optionally, you could clear the fields here if you prefer that UX:
			// companyNameField.val('');
			// addressField.val('');
			// lastFetchedIco = null; // If you clear fields, also clear the last fetched ICO
		} else if (
			!lastFetchedIco &&
			(companyNameField.val() !== "" || addressField.val() !== "")
		) {
			// This handles the case where fields might have been pre-filled or filled by other means
			// and then the IČO is changed before any ARES lookup
			statusSpan
				.text('Zadejte IČO a klikněte na "Načíst údaje".')
				.css("color", ""); // Clear status or set default
		} else if (currentIco === "" && lastFetchedIco) {
			// If IČO is cleared, reset status and potentially fields
			statusSpan.text("").css("color", "");
			// companyNameField.val(''); // Optional
			// addressField.val('');   // Optional
			// lastFetchedIco = null;
		}
	});
});
