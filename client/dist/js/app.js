(function (factory) {
  typeof define === 'function' && define.amd ? define('app', factory) :
  factory();
}((function () { 'use strict';

  function _unsupportedIterableToArray(o, minLen) {
    if (!o) return;
    if (typeof o === "string") return _arrayLikeToArray(o, minLen);
    var n = Object.prototype.toString.call(o).slice(8, -1);
    if (n === "Object" && o.constructor) n = o.constructor.name;
    if (n === "Map" || n === "Set") return Array.from(o);
    if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen);
  }

  function _arrayLikeToArray(arr, len) {
    if (len == null || len > arr.length) len = arr.length;

    for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i];

    return arr2;
  }

  function _createForOfIteratorHelper(o, allowArrayLike) {
    var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"];

    if (!it) {
      if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") {
        if (it) o = it;
        var i = 0;

        var F = function () {};

        return {
          s: F,
          n: function () {
            if (i >= o.length) return {
              done: true
            };
            return {
              done: false,
              value: o[i++]
            };
          },
          e: function (e) {
            throw e;
          },
          f: F
        };
      }

      throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
    }

    var normalCompletion = true,
        didErr = false,
        err;
    return {
      s: function () {
        it = it.call(o);
      },
      n: function () {
        var step = it.next();
        normalCompletion = step.done;
        return step;
      },
      e: function (e) {
        didErr = true;
        err = e;
      },
      f: function () {
        try {
          if (!normalCompletion && it.return != null) it.return();
        } finally {
          if (didErr) throw err;
        }
      }
    };
  }

  /**
   * Inline Link mutation observer
   * Use MutationObserver to detect when the link type selector changes
   * See InlineLinkField.php for an example using data-signals attribute
   */

  /**
   * fire a signal, triggered by the trigger element
   * Depending on the results, the srcElement (or its container is shown/hidden)
   */
  var fireSignal = function fireSignal(triggerElement, srcElement, signal) {
    // the container that will be applied
    var containerElement = srcElement;

    if (signal.containerSelector) {
      // apply the container as the closest element
      containerElement = srcElement.closest(signal.containerSelector);
    } // value hit (based on OR)


    var hit = signal.value.includes(triggerElement.value);

    if (hit) {
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


  var isBound = function isBound(triggerElement, srcElement) {
    if (!triggerElement.dataset.knownSignals) {
      return false;
    } // knownSignals is a string


    var knownSignals = JSON.parse(triggerElement.dataset.knownSignals);

    try {
      var exists = knownSignals.find(function (item) {
        return item.id == srcElement.id;
      });
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


  var bindElements = function bindElements(triggerElement, srcElement) {
    if (!srcElement.id) {
      console.warning('srcElement must have an id attributes');
      return false;
    }

    var knownSignals = [];

    if (!triggerElement.dataset.knownSignals) {
      triggerElement.dataset.knownSignals = '';
    } else {
      knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
    }

    var isSrcBound = isBound(triggerElement, srcElement);

    if (!isSrcBound) {
      knownSignals.push({
        id: srcElement.id
      }); // save to the element

      triggerElement.dataset.knownSignals = JSON.stringify(knownSignals); // bind the DOM event

      triggerElement.addEventListener('change', function (e) {
        // fire all signals on the event
        fireSignals(this);
      });
    }
  };
  /**
   * Fire all known signals on a trigger element in sequence
   */


  var fireSignals = function fireSignals(triggerElement) {
    try {
      var knownSignals = [];

      if (triggerElement.dataset.knownSignals) {
        knownSignals = JSON.parse(triggerElement.dataset.knownSignals);
      }

      if (knownSignals.length == 0) {
        return;
      } // fire each signal


      var _iterator = _createForOfIteratorHelper(knownSignals),
          _step;

      try {
        var _loop = function _loop() {
          var entry = _step.value;
          var srcElement = document.getElementById(entry.id);

          if (!srcElement) {
            console.warn('srcElement from entry.id does not exist:' + entry.id);
          } else {
            var signals = JSON.parse(srcElement.dataset.signals);
            signals.forEach(function (signal) {
              fireSignal(triggerElement, srcElement, signal);
            });
          }
        };

        for (_iterator.s(); !(_step = _iterator.n()).done;) {
          _loop();
        }
      } catch (err) {
        _iterator.e(err);
      } finally {
        _iterator.f();
      }
    } catch (e) {
      console.warn(e);
    }
  };
  /**
   * Apply signals from a src element
   */


  var applySignals = function applySignals(srcElement) {
    try {
      var signals = JSON.parse(srcElement.dataset.signals);
      signals.forEach(function (signal) {
        applySignal(srcElement, signal);
      });
    } catch (e) {
      console.warn(e);
    }
  };
  /**
   * Apply a single signal from a src element
   */


  var applySignal = function applySignal(srcElement, signal) {
    if (!srcElement.id) {
      console.warn('srcElement must have an ID attribute value');
      return false;
    } // based on form element


    var elementForm = srcElement.closest('form');

    if (elementForm) {
      var triggerElement = elementForm.querySelector('[name="' + signal.triggerElement + '"]');

      if (triggerElement) {
        // fire the signal right away
        fireSignal(triggerElement, srcElement, signal); // and bind the srcElement to the triggerElement

        bindElements(triggerElement, srcElement);
      } else {
        console.warn('triggerElement not found: ' + signal.triggerElement);
      }
    } else {
      console.warn('Elements outside of forms are not yet supported'); // todo: support changes on non-form elements
    }
  };
  /**
   * Add the Mutation Observer to the document
   */


  var addDocumentObserver = function addDocumentObserver() {
    var documentObserver = new MutationObserver(function (mutations, observer) {
      var _iterator2 = _createForOfIteratorHelper(mutations),
          _step2;

      try {
        for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
          var mutation = _step2.value;

          switch (mutation.type) {
            case 'attributes':
              if (mutation.attributeName == 'data-signals' || mutation.attributeName == 'data-init-signal') {
                // initSignal in dataset triggers dataset.signals to be observed
                // as it is in the list of attributes to observe
                // push the rule onto the available signals
                applySignals(mutation.target);
              } else if (mutation.attributeName == 'class') {
                // some elements add/remove a 'changed' class e.g chosen
                var isChanged = mutation.target.classList.contains('changed');
                var wasChanged = mutation.oldValue && mutation.oldValue.split(' ').includes('changed');

                if (isChanged || wasChanged) {
                  if (mutation.target.nodeName == 'SELECT') {
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
              var _iterator3 = _createForOfIteratorHelper(mutation.addedNodes),
                  _step3;

              try {
                for (_iterator3.s(); !(_step3 = _iterator3.n()).done;) {
                  var addedNode = _step3.value;

                  try {
                    if (addedNode.dataset && addedNode.dataset.signals) {
                      applySignals(mutation.target);
                    }
                  } catch (e) {
                    console.warn(e);
                  }
                }
              } catch (err) {
                _iterator3.e(err);
              } finally {
                _iterator3.f();
              }

              break;

            default:
              console.warn('documentObserver unhandled mutation type: ' + mutation.type);
              break;
          } //switch

        } // for

      } catch (err) {
        _iterator2.e(err);
      } finally {
        _iterator2.f();
      }
    } // function
    ); // ckass
    // observe the document + subtree for changes

    documentObserver.observe(document, {
      attributes: true,
      // observe attributes
      attributeOldValue: true,
      attributeFilter: ["class", "data-signals"],
      // restrict mutation check
      subtree: true,
      // observer subtree under document
      childList: false // do not observe child nodes (for now)

    });
  }; // start


  addDocumentObserver(); // provide a callback to trigger initiation

  var initElements = function initElements() {
    var elements = document.querySelectorAll('[data-signals]');
    elements.forEach(function (element) {
      // trigger a mutation
      var signals = element.dataset.signals;
      element.dataset.signals = signals;
    });
  }; // handle initial DOMContentLoaded


  window.document.addEventListener('DOMContentLoaded', function () {
    initElements();
  }); // integrate with entwine, if available

  if (typeof window.jQuery != 'undefined' && window.jQuery.fn.entwine) {
    window.jQuery.entwine('ss', function ($) {
      $('.cms-edit-form').entwine({
        onmatch: function onmatch() {
          initElements();
        }
      });
    });
  }

})));

//# sourceMappingURL=app.js.map
