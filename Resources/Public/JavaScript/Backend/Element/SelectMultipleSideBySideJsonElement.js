define(
  ["TYPO3/CMS/Backend/FormEngine"],
  (function (FormEngine) {
    FormEngine.updateHiddenFieldValueFromSelect = function (selectFieldEl, originalFieldEl) {
      var selectedValues = [];
      $(selectFieldEl).find('option').each(function () {
        selectedValues.push($(this).prop('value'));
      });

      // make a comma separated list, if it is a multi-select
      // set the values to the final hidden field
      if (originalFieldEl.dataset.separator === 'json') {
        originalFieldEl.value = JSON.stringify(selectedValues);
      } else {
        originalFieldEl.value = selectedValues.join(',');
      }
      originalFieldEl.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
    };
  })
)
