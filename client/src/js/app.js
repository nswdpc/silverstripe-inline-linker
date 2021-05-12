/**
 * Inline Link mutation observer
 * Use MutationObserver to detect when the link type selector changes
 * See InlineLinkField.php for an example using data-signals attribute
 */

/**
 * fire a signal, triggered by the trigger element
 * Depending on the results, the srcElement (or its container is shown/hidden)
 */
const fireSignal = function(triggerElement, srcElement, signal) {

  // the container that will be applied
  let containerElement = srcElement;
  if (signal.containerSelector) {
    // apply the container as the closest element
    containerElement = srcElement.closest(signal.containerSelector);
  }

  // value hit (based on OR)
  const hit = signal.value.includes(
    triggerElement.value
  );

  if(hit) {
    containerElement.classList.remove('hidden');
  } else {
    // remove if set, add if not
    containerElement.classList.add('hidden');
  }
  return;
};

/**
 * Determine if the srcElement is bound to the triggerElement
 */
const isBound = function(triggerElement, srcElement) {
  if (!triggerElement.dataset.knownSignals) {
    return false;
  }
  // knownSignals is a string
  const knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
  try {
    const exists = knownSignals.find(
      function(item) {
        return item.id == srcElement.id;
      }
    );
    return exists;
  } catch (e) {
    console.warn('isBound failed', e);
    return false;
  }
};

/**
 * Bind the srcElement ot the triggerElement
 * So that when the trigger Element change event is fired the signals from
 * the src element are fired
 */
const bindElements = function(triggerElement, srcElement) {

  if (!srcElement.id) {
    console.warning('srcElement must have an id attributes');
    return false;
  }

  let knownSignals = [];
  if (!triggerElement.dataset.knownSignals) {
    triggerElement.dataset.knownSignals = '';
  } else {
    knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
  }

  const isSrcBound = isBound(triggerElement, srcElement);
  if (!isSrcBound) {
    knownSignals.push({
      id: srcElement.id
    });

    // save to the element
    triggerElement.dataset.knownSignals = JSON.stringify(knownSignals);

    // bind the DOM event
    triggerElement.addEventListener(
      'change',
      function(e) {
        // fire all signals on the event
        fireSignals(this);
      }
    );

  }

};

/**
 * Fire all known signals on a trigger element in sequence
 */
const fireSignals = function(triggerElement) {

  try {
    let knownSignals = [];
    if (triggerElement.dataset.knownSignals) {
      knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
    }
    if(knownSignals.length == 0) {
      return;
    }

    // fire each signal
    for (let entry of knownSignals) {
      let srcElement = document.getElementById(entry.id);
      if (!srcElement) {
        console.warn('srcElement from entry.id does not exist:' + entry.id);
      } else {
        let signals = JSON.parse(srcElement.dataset.signals);
        signals.forEach((signal) => {
          fireSignal(triggerElement, srcElement, signal);
        });
      }
    }
  } catch (e) {
    console.warn(e);
  }
};

/**
 * Apply signals from a src element
 */
const applySignals = function(srcElement) {
  try {
    const signals = JSON.parse(srcElement.dataset.signals);
    signals.forEach((signal) => {
      applySignal(srcElement, signal);
    });
  } catch (e) {
    console.warn(e);
  }
};

/**
 * Apply a single signal from a src element
 */
const applySignal = function(srcElement, signal) {
  if (!srcElement.id) {
    console.warn('srcElement must have an ID attribute value');
    return false;
  }
  // based on form element
  const elementForm = srcElement.closest('form');
  if (elementForm) {
    const triggerElement = elementForm.querySelector('[name="' + signal.triggerElement + '"]');
    if (triggerElement) {
      // fire the signal right away
      fireSignal(triggerElement, srcElement, signal);
      // and bind the srcElement to the triggerElement
      bindElements(triggerElement, srcElement);
    } else {
      console.warn('triggerElement not found: ' + signal.triggerElement);
    }
  } else {
    console.warn('Elements outside of forms are not yet supported');
    // todo: support changes on non-form elements
  }
};

/**
 * Add the Mutation Observer to the document
 */
const addDocumentObserver = function() {

  const documentObserver = new MutationObserver(
    function(mutations, observer) {

      for (const mutation of mutations) {
        switch (mutation.type) {
          case 'attributes':

            if(mutation.attributeName == 'data-signals' || mutation.attributeName == 'data-init-signal') {
              // initSignal in dataset triggers dataset.signals to be observed
              // as it is in the list of attributes to observe
              // push the rule onto the available signals
              applySignals(mutation.target);
            } else if(mutation.attributeName == 'class') {
              // some elements add/remove a 'changed' class e.g chosen
              let isChanged = mutation.target.classList.contains('changed');
              let wasChanged = mutation.oldValue && mutation.oldValue.split(' ').includes('changed');
              if(isChanged || wasChanged) {
                if(mutation.target.nodeName == 'SELECT') {
                  // mutation.target is the triggerElement
                  fireSignals(mutation.target);
                } else {
                  console.warn('Ignoring changed class on: ' + mutation.target.nodeName);
                }
              }
            } else {
              console.warn('Unhandled attribute: ' + mutation.attributeName);
            }
            break;
          case 'childList':
            // TODO
            for (const addedNode of mutation.addedNodes) {
              try {
                if (addedNode.dataset && addedNode.dataset.signals) {
                  applySignals(mutation.target);
                }
              } catch (e) {
                console.warn(e);
              }
            }
            break;
          default:
            console.warn('documentObserver unhandled mutation type: ' + mutation.type);
            break;
        } //switch
      } // for
    } // function
  ); // ckass

  // observe the document + subtree for changes
  documentObserver.observe(
    document, {
      attributes: true, // observe attributes
      attributeOldValue: true,
      attributeFilter: ["class", "data-signals"], // restrict mutation check
      subtree: true,// observer subtree under document
      childList: false // do not observe child nodes (for now)
    }
  );

};

// start
addDocumentObserver();

// provide a callback to trigger initiation
const initElements = function() {
  const elements = document.querySelectorAll('[data-signals]');
  elements.forEach((element) => {
    // trigger a mutation
    let signals = element.dataset.signals;
    element.dataset.signals = signals;
  });
};

// handle initial DOMContentLoaded
window.document.addEventListener(
  'DOMContentLoaded', () => {
    initElements();
  }
);

// integrate with entwine, if available
if (typeof window.jQuery != 'undefined' && window.jQuery.fn.entwine) {
  window.jQuery.entwine('ss', ($) => {
    $('.cms-edit-form').entwine({
      onmatch() {
        initElements();
      }
    });
  });
}
