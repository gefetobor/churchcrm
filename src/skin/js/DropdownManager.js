/**
 * Dropdown Manager - Centralized country/state dropdown initialization
 * Used across PersonEditor, CartToFamily, CSVImport, FamilyEditor, and FamilyRegister
 */

class DropdownManager {
  /**
   * Initialize a country dropdown with API data
   * @param {string} countrySelectId - ID of the country select element
   * @param {string} stateSelectId - Optional ID of the state select element (for auto-cascade)
   * @param {Object} options - Configuration options
   * @param {string} options.userSelected - Pre-selected country value
   * @param {string} options.systemDefault - Default country from system config
   * @param {boolean} options.initSelect2 - Whether to initialize select2 (default: true)
   * @param {boolean} options.cascadeState - Whether to cascade to state select on change (default: false)
   * @param {Function} options.onCountryChange - Callback when country changes
   */
  static initializeCountry(countrySelectId, stateSelectId = null, options = {}) {
    const countrySelect = $(`#${countrySelectId}`);
    if (countrySelect.length === 0) return;

    const defaults = {
      userSelected: countrySelect.data("user-selected") || "",
      systemDefault: countrySelect.data("system-default") || "",
      initSelect2: true,
      cascadeState: !!stateSelectId,
      onCountryChange: null,
    };

    const config = { ...defaults, ...options };

    // Fetch and populate countries
    $.ajax({
      type: "GET",
      url: window.CRM.root + "/api/public/data/countries",
    }).done(function (data) {
      countrySelect.empty();

      $.each(data, function (idx, country) {
        let selected = false;

        if (config.userSelected === "") {
          selected = config.systemDefault === country.name || config.systemDefault === country.code;
        } else {
          selected = config.userSelected === country.name || config.userSelected === country.code;
        }

        countrySelect.append(new Option(country.name, country.code, selected, selected));
      });

      // Trigger change to cascade to state if needed
      countrySelect.change();

      if (config.initSelect2) {
        countrySelect.select2();
      }
    });

    // Handle cascading to state dropdown if configured
    if (config.cascadeState) {
      countrySelect.off("change").on("change", function () {
        DropdownManager.initializeState(stateSelectId, this.value.toLowerCase(), {
          userSelected: $(`#${stateSelectId}`).data("user-selected") || "",
          systemDefault: $(`#${stateSelectId}`).data("system-default") || "",
          initSelect2: true,
          stateTextboxId: config.stateTextboxId,
          stateOptionDivId: config.stateOptionDivId,
          stateInputDivId: config.stateInputDivId,
        });

        if (config.onCountryChange) {
          config.onCountryChange(this.value);
        }
      });
    }
  }

  /**
   * Initialize a state dropdown with API data
   * @param {string} stateSelectId - ID of the state select element
   * @param {string} countryCode - Country code to fetch states for
   * @param {Object} options - Configuration options
   * @param {string} options.userSelected - Pre-selected state value
   * @param {string} options.systemDefault - Default state from system config
   * @param {boolean} options.initSelect2 - Whether to initialize select2 (default: true)
   * @param {string} options.stateTextboxId - Optional ID of textbox fallback for countries without states
   * @param {string} options.stateOptionDivId - Optional ID of div to show/hide for state dropdown
   * @param {string} options.stateInputDivId - Optional ID of div to show/hide for state textbox
   */
  static initializeState(stateSelectId, countryCode, options = {}) {
    const stateSelect = $(`#${stateSelectId}`);
    if (stateSelect.length === 0) return;

    const defaults = {
      userSelected: stateSelect.data("user-selected") || "",
      systemDefault: stateSelect.data("system-default") || "",
      initSelect2: true,
      stateTextboxId: null,
      stateOptionDivId: null,
      stateInputDivId: null,
    };

    const config = { ...defaults, ...options };

    // Fetch and populate states
    $.ajax({
      type: "GET",
      url: window.CRM.root + "/api/public/data/countries/" + countryCode + "/states",
    })
      .done(function (data) {
        if (Object.keys(data).length > 0) {
          // Country has states - populate dropdown
          stateSelect.empty();

          $.each(data, function (code, name) {
            let selected = false;

            if (config.userSelected === "") {
              selected = config.systemDefault === name || config.systemDefault === code;
            } else {
              selected = config.userSelected === name || config.userSelected === code;
            }

            stateSelect.append(new Option(name, code, selected, selected));
          });

          stateSelect.change();

          if (config.initSelect2) {
            stateSelect.select2();
          }

          // Show state dropdown, hide textbox fallback
          if (config.stateOptionDivId) {
            $(`#${config.stateOptionDivId}`).removeClass("d-none");
          }
          if (config.stateInputDivId) {
            $(`#${config.stateInputDivId}`).addClass("d-none");
          }
          if (config.stateTextboxId) {
            $(`#${config.stateTextboxId}`).val("");
          }
        } else {
          // Country has no states - show textbox instead
          if (config.stateOptionDivId) {
            $(`#${config.stateOptionDivId}`).addClass("d-none");
          }
          if (config.stateInputDivId) {
            $(`#${config.stateInputDivId}`).removeClass("d-none");
          }
        }
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        window.CRM.notify(
          i18next.t("Unable to load state list. Please check your network connection or try again later."),
          { type: "error", delay: 5000 },
        );
        // Optionally, show textbox fallback if config.stateInputDivId is set
        if (config.stateOptionDivId) {
          $(`#${config.stateOptionDivId}`).addClass("d-none");
        }
        if (config.stateInputDivId) {
          $(`#${config.stateInputDivId}`).removeClass("d-none");
        }
      });
  }

  /**
   * Initialize both country and state dropdowns with cascading
   * @param {string} countrySelectId - ID of the country select element
   * @param {string} stateSelectId - ID of the state select element
   * @param {Object} options - Configuration options (merged with country and state options)
   */
  static initializeCountryState(countrySelectId, stateSelectId, options = {}) {
    // Initialize country with state cascading
    this.initializeCountry(countrySelectId, stateSelectId, {
      userSelected: options.userSelected || $(`#${countrySelectId}`).data("user-selected") || "",
      systemDefault: options.systemDefault || $(`#${countrySelectId}`).data("system-default") || "",
      initSelect2: true,
      cascadeState: true,
      ...options,
    });
  }

  /**
   * Initialize FamilyRegister-style country/state with dynamic container
   * @param {string} countrySelectId - ID of the country select element
   * @param {string} stateContainerId - ID of the container div for state field
   * @param {string} stateFieldId - ID of the state field (input or select)
   * @param {Object} options - Configuration options
   */
  static initializeFamilyRegisterCountryState(countrySelectId, stateContainerId, stateFieldId, options = {}) {
    const countrySelect = $(`#${countrySelectId}`);
    const stateContainer = $(`#${stateContainerId}`);

    if (countrySelect.length === 0 || stateContainer.length === 0) return;

    const defaults = {
      userSelected: countrySelect.data("user-selected") || "",
      systemDefault: countrySelect.data("system-default") || "",
      stateDefault: $(`#${stateFieldId}`).data("default") || "",
    };

    const config = { ...defaults, ...options };

    const resolveDefaultCountryCode = function (value) {
      const v = (value || "").toString().trim().toUpperCase();
      return v === "" ? "GB" : v;
    };

    const getStatesUrl = function (countryCode) {
      const root = ((window.CRM && window.CRM.root) || "").replace(/\/$/, "");
      return `${root}/api/public/data/countries/${countryCode.toLowerCase()}/states`;
    };

    const populateStatesForCountry = function (countryCode) {
      if (!countryCode) {
        return;
      }

      $.ajax({
        type: "GET",
        url: getStatesUrl(countryCode),
      })
        .done(function (data) {
          const defaultState = config.stateDefault || "";
          const existingStateValue = ($(`#${stateFieldId}`).val() || "").toString().trim();

          if (Object.keys(data).length > 0) {
            // Country has states - show dropdown
            const $select = $(
              `<select id="${stateFieldId}" name="${stateFieldId}" class="form-control" data-default="${defaultState}"></select>`,
            );
            let appliedState = false;

            $.each(data, function (code, name) {
              const $option = $("<option></option>").val(code).text(name);
              if (existingStateValue !== "" && (existingStateValue === code || existingStateValue === name)) {
                $option.prop("selected", true);
                appliedState = true;
              } else if (!appliedState && (defaultState === code || defaultState === name)) {
                $option.prop("selected", true);
                appliedState = true;
              }
              $select.append($option);
            });

            if (!appliedState && $select.find("option").length > 0) {
              $select.find("option").first().prop("selected", true);
            }

            stateContainer.html($select);
          } else {
            // Country has no states - show text input
            const $input = $(
              `<input type="text" id="${stateFieldId}" name="${stateFieldId}" class="form-control" data-default="${defaultState}">`,
            );
            if (existingStateValue) {
              $input.val(existingStateValue);
            } else if (defaultState) {
              $input.val(defaultState);
            }
            stateContainer.html($input);
          }
        })
        .fail(function () {
          const $fallbackInput = $(
            `<input type="text" id="${stateFieldId}" name="${stateFieldId}" class="form-control" data-default="${config.stateDefault || ""}">`,
          );
          $fallbackInput.val(config.stateDefault || "");
          $fallbackInput.attr("placeholder", "State / Province");
          stateContainer.html($fallbackInput);
        });
    };

    const handleCountryChange = function (explicitCountryCode = "") {
      const selectedCountryCode = (explicitCountryCode || countrySelect.val() || "").toString().trim();
      if (selectedCountryCode === "") {
        return;
      }
      populateStatesForCountry(selectedCountryCode);
    };

    // Handle country change (native + Select2 events)
    countrySelect
      .off("change.familyRegister select2:select.familyRegister select2:close.familyRegister")
      .on("change.familyRegister", function () {
        handleCountryChange();
      })
      .on("select2:select.familyRegister", function (e) {
        const selectedCode = e && e.params && e.params.data ? e.params.data.id || e.params.data.value || "" : "";
        handleCountryChange(selectedCode);
      })
      .on("select2:close.familyRegister", function () {
        handleCountryChange();
      });

    // Native DOM listener as an additional safeguard.
    const nativeCountrySelect = countrySelect.get(0);
    if (nativeCountrySelect) {
      nativeCountrySelect.onchange = function () {
        handleCountryChange(nativeCountrySelect.value || "");
      };
    }

    // Keep server-rendered country list and initialize deterministic defaults.
    const defaultCountryCode = resolveDefaultCountryCode(config.systemDefault);
    if (
      (countrySelect.val() || "").toString().trim() === "" &&
      countrySelect.find(`option[value="${defaultCountryCode}"]`).length > 0
    ) {
      countrySelect.val(defaultCountryCode);
    }

    if (countrySelect.hasClass("select2-hidden-accessible")) {
      countrySelect.select2("destroy");
    }
    countrySelect.select2({ width: "100%" });

    // Initial state population for the preselected country.
    const initialCountryCode = (countrySelect.val() || defaultCountryCode).toString().trim();
    populateStatesForCountry(initialCountryCode);

    // Last-resort watcher: if country value changes without event propagation,
    // still repopulate states.
    let lastCountryCode = initialCountryCode;
    const watcherId = window.setInterval(function () {
      const currentCountryCode = (countrySelect.val() || "").toString();
      if (currentCountryCode !== "" && currentCountryCode !== lastCountryCode) {
        lastCountryCode = currentCountryCode;
        populateStatesForCountry(currentCountryCode);
      }
    }, 250);

    window.addEventListener(
      "beforeunload",
      function () {
        window.clearInterval(watcherId);
      },
      { once: true },
    );
  }
}

// jQuery-style plugin initialization shorthand
$.fn.initializeCountryDropdown = function (options) {
  this.each(function () {
    DropdownManager.initializeCountry($(this).attr("id"), null, options);
  });
  return this;
};

$.fn.initializeStateDropdown = function (countryCode, options) {
  this.each(function () {
    DropdownManager.initializeState($(this).attr("id"), countryCode, options);
  });
  return this;
};
