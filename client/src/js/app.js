/**
 * Inline Link mutation observer
 * Use MutationObserver to detect when the link type selector changes
 * See InlineLinkField.php for an example using data-signals attribute
 */
(function() {

  class InlineLinkField {

    /**
     * fire a signal, triggered by the trigger element
     * Depending on the results, the srcElement (or its container is shown/hidden)
     */
    fireSignal(triggerElement, srcElement, signal) {

      // the container that will be applied
      let containerElement = srcElement;
      if (signal.containerSelector) {
        // apply the container as the closest element
        containerElement = srcElement.closest(signal.containerSelector);
        if(!containerElement) {
          throw 'No containerElement for selector ' + signal.containerSelector;
        }
      }
      // value hit (based on OR)
      let hit = signal.value.includes(
        triggerElement.value
      );

      if(hit) {
        containerElement.classList.remove('signal-hidden');
      } else {
        containerElement.classList.add('signal-hidden');
      }
      return;
    }

    /**
     * Determine if the srcElement is bound to the triggerElement
     */
    isBound(triggerElement, srcElement) {
      if (!triggerElement.dataset.knownSignals) {
        return false;
      }
      // knownSignals is a string
      let knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
      try {
        let exists = knownSignals.find(
          function(item) {
            return item.id == srcElement.id;
          }
        );
        return exists;
      } catch (e) {
        console.warn('isBound failed', e);
        return false;
      }
    }

    /**
     * Bind the srcElement ot the triggerElement
     * So that when the trigger Element change event is fired the signals from
     * the src element are fired
     */
    bindElements(triggerElement, srcElement) {

      if (!srcElement.id) {
        console.warning('srcElement must have an id attribute');
        return false;
      }

      let _self = this;
      let knownSignals = [];
      if (!triggerElement.dataset.knownSignals) {
        triggerElement.dataset.knownSignals = '';
      } else {
        knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
      }

      let isSrcBound = this.isBound(triggerElement, srcElement);
      if (!isSrcBound) {

        knownSignals.push({
          id: srcElement.id
        });

        // save to the element
        triggerElement.dataset.knownSignals = JSON.stringify(knownSignals);

        // bind the DOM change event once
        if(!triggerElement.dataset.changeBound) {
          triggerElement.addEventListener(
            'change',
            function(e) {
              // fire all signals on the event
              _self.fireSignals(this);
            }
          );
          triggerElement.dataset.changeBound = 1;
        }

      }

    }

    /**
     * Fire all known signals on a trigger element in sequence
     */
    fireSignals(triggerElement) {

      try {
        let _self = this;
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
              _self.fireSignal(triggerElement, srcElement, signal);
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
    applySignals(srcElement) {
      try {
        let _self = this;
        let signals = JSON.parse(srcElement.dataset.signals);
        signals.forEach((signal) => {
          _self.applySignal(srcElement, signal);
        });
      } catch (e) {
        console.warn(e);
      }
    };

    /**
     * Apply a single signal from a src element
     */
    applySignal(srcElement, signal) {
      if (!srcElement.id) {
        console.warn('srcElement must have an ID attribute value');
        return false;
      }
      // based on form element
      let elementForm = srcElement.closest('form');
      if (elementForm) {
        let triggerElement = elementForm.querySelector('[name="' + signal.triggerElement + '"]');
        if (triggerElement) {
          // fire the signal right away
          this.fireSignal(triggerElement, srcElement, signal);
          // and bind the srcElement to the triggerElement
          this.bindElements(triggerElement, srcElement);
        } else {
          console.warn('triggerElement not found: ' + signal.triggerElement);
        }
      } else {
        console.warn('Elements outside of forms are not yet supported');
        // todo: support changes on non-form elements
      }
    }

    /**
     * Add the Mutation Observer to the document
     */
    addDocumentObserver() {

      let _self = this;
      let documentObserver = new MutationObserver(
        function(mutations, observer) {

          for (let mutation of mutations) {
            switch (mutation.type) {
              case 'attributes':
                if(mutation.attributeName == 'data-signals' || mutation.attributeName == 'data-init-signal') {
                  // initSignal in dataset triggers dataset.signals to be observed
                  // as it is in the list of attributes to observe
                  // push the rule onto the available signals
                  _self.applySignals(mutation.target);
                }
                break;
              case 'childList':
                /*
                // TODO
                for (let addedNode of mutation.addedNodes) {
                  try {
                    if (addedNode.dataset && addedNode.dataset.signals) {
                      _self.applySignals(mutation.target);
                    }
                  } catch (e) {
                    console.warn(e);
                  }
                }
                */
                break;
              default:
                console.warn('documentObserver unhandled mutation type: ' + mutation.type);
                break;
            }
          }
        }
      );

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

    }

  }

  // listener method
  let inlineLinkFieldListener = function() {

    // provide a callback to trigger initiation
    let initElements = function() {
      let elements = document.querySelectorAll('[data-signals]');
      elements.forEach((element) => {
        // trigger a mutation
        let signals = element.dataset.signals;
        element.dataset.signals = signals;
      });
    };

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
        $('.inlinelinkcomposite').entwine({
          onmatch() {
            initElements();
          }
        });
      });
    }

  }

  // start observing
  let inlineLinkField = new InlineLinkField();
  inlineLinkField.addDocumentObserver();
  // event listener
  inlineLinkFieldListener();

}());
