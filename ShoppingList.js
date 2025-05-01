let shoppingList = {};

$(document).ready(function () {

	// Initial call to attach click event for adjusting yield
	makeYieldEditable();
		
});


function toggleIngredientList(event) {
    if (event.target.tagName === 'P' && event.target.classList.contains('ingredient') && document.getElementById('ingredients').classList.contains('ActiveList')) {
        event.target.classList.toggle('InList');
        updateShoppingList(event.target, event.target.classList.contains('InList'));
        updateSession();
    } else if (event.target.parentNode && event.target.parentNode.tagName === 'P' && event.target.parentNode.classList.contains('ingredient') && document.getElementById('ingredients').classList.contains('ActiveList')) {
        event.target.parentNode.classList.toggle('InList');
        updateShoppingList(event.target.parentNode, event.target.parentNode.classList.contains('InList'));
        updateSession();
    }
}


document.getElementById('ingredients').addEventListener('click', toggleIngredientList);

document.getElementById('AddToList').addEventListener('click', function() {
  // Add ActiveList class to div#ingredients
  document.getElementById('ingredients').classList.add('ActiveList');
  
  // Add ingredients to the shopping list
  let ingredients = document.getElementById('ingredients').querySelectorAll('p.ingredient');
  ingredients.forEach(ingredient => {
    ingredient.classList.add('InList');
    updateShoppingList(ingredient, true);
  });
  
  // Update PHP session via AJAX
  updateSession();
  
  // Replace Add button with Remove button and show "Added to list" text
  this.style.display = 'none';
  document.getElementById('RemoveFromList').style.display = 'block';
  document.getElementById('AddedToList').style.display = 'block';
});

document.getElementById('RemoveFromList').addEventListener('click', function() {
  // Remove ActiveList class from div#ingredients
  document.getElementById('ingredients').classList.remove('ActiveList');
  
  // Remove ingredients from the shopping list
  let ingredients = document.getElementById('ingredients').querySelectorAll('p.ingredient');
  ingredients.forEach(ingredient => {
    ingredient.classList.remove('InList');
    updateShoppingList(ingredient, false);
  });
  
  // Update PHP session via AJAX
  updateSession();
  
  // Replace Remove button with Add button and hide "Added to list" text
  this.style.display = 'none';
  document.getElementById('AddToList').style.display = 'block';
  document.getElementById('AddedToList').style.display = 'none';
});

function updateShoppingList(ingredient, add) {
  let recipeId = ingredient.dataset.recipeid;
  let componentId = ingredient.dataset.recipeingredientid;
  let quantity = ingredient.dataset.quantity;
  
  if (add) {
    if (!shoppingList[recipeId]) {
      shoppingList[recipeId] = {};
    }
    shoppingList[recipeId][componentId] = {
      componentId: componentId,
      quantity: quantity
    };
  } else {
    if (shoppingList[recipeId] && shoppingList[recipeId][componentId]) {
      delete shoppingList[recipeId][componentId];
      if (Object.keys(shoppingList[recipeId]).length === 0) {
        delete shoppingList[recipeId];
      }
    }
  }
  
  // Send data to server with action
  let data = {
    action: add ? 'Add' : 'Delete',
    ingredient: {
      recipeId: recipeId,
      componentId: componentId,
      quantity: quantity
    }
  };
  
  updateSession(data);
}

function updateSession(data = null) {
  if (data === null) {
    data = {
      action: 'GetList',
      list: shoppingList
    };
  }
  
  fetch('/ajax/ListAJAX.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.text(); // First read as text to handle potential errors
  })
  .then(text => {
    try {
      let data = JSON.parse(text);
      if (data.success) {
        console.log('Shopping List:', data.shoppingList); // Log the shopping list
      } else {
        console.error('Error:', data.message);
      }
    } catch (error) {
      console.error('Error parsing JSON:', error);
      console.log('Response Text:', text); // Log the actual response text
    }
  })
  .catch(error => console.error('Error:', error));
}

// Function to make #Yield editable and handle updates
function makeYieldEditable() {
  let originalYield = parseFloat($('#Yield').data('originalyield')); // Retrieve original yield from data attribute
  
  $('#Yield').on('click', function () {
    let $yieldQuantity = $('#YieldQuantity');
    let currentValue = $yieldQuantity.text().trim();
    
    // Create an input field with seamless styling
    let $input = $('<input>', {
      type: 'text', // Change to text to allow custom input handling
      id: 'YieldInput',
      css: {
        fontFamily: 'inherit',
        fontSize: 'inherit',
        width: '3em', // Adjust width to be less disruptive
        textAlign: 'center'
      }
    });
    
    // Remove up and down buttons
    $input.attr('style', 'appearance: none; -moz-appearance: textfield;');

    // Restrict input to numbers and a single decimal point
    $input.on('input', function() {
      let value = $(this).val();
      let regex = /^(\d+(\.\d{0,1})?|\.\d{1})$/;
      if (!regex.test(value)) {
        $(this).val(value.replace(/[^0-9\.]/g, '').replace(/\.(?=.*\.)/g, ''));
      }
    });

    $yieldQuantity.replaceWith($input);
    $input.val(''); // Ensure input field is empty
    $input.focus();
    
    // Handle blur or "Enter" key events
    $input.on('blur keypress', function (e) {
      if (e.type === 'blur' || (e.type === 'keypress' && e.which === 13)) {
        let newValue = parseFloat($input.val().trim());
        
        if (!isNaN(newValue) && newValue > 0) {
          $input.replaceWith(`<span id="YieldQuantity">${newValue}</span>`);
          
          // Update quantities in the recipe dynamically
          $('span.Quantity').each(function () {
            let originalQuantity = parseFloat($(this).data('originalquantity')); // Use data attribute
            
            let updatedQuantity = (originalQuantity / originalYield) * newValue;
            
            // Round to nearest whole number if greater than 15
            if (updatedQuantity > 15) {
              updatedQuantity = Math.round(updatedQuantity);
            }
            
            $(this).text(formatNumber(updatedQuantity));
            
            // Update data-quantity attribute for each ingredient
            let ingredient = $(this).closest('p.ingredient');
            if (ingredient.length > 0) {
              ingredient.attr('data-quantity', formatNumber(updatedQuantity));
            }
          });
          
          originalYield = newValue; // Update the original yield variable
          $('#Yield').data('originalyield', newValue); // Update data attribute with new yield
          
          // If the ingredients list is active, update the shopping list
          if (document.getElementById('ingredients').classList.contains('ActiveList')) {
            let ingredients = document.getElementById('ingredients').querySelectorAll('p.ingredient.InList');
            ingredients.forEach(ingredient => {
              updateShoppingList(ingredient, true);
            });
            updateSession({
              action: 'Update',
              ingredients: Array.from(ingredients).map(ingredient => ({
                recipeId: ingredient.dataset.recipeid,
                componentId: ingredient.dataset.componentid,
                quantity: ingredient.dataset.quantity,
                unitId: ingredient.dataset.unit,
                componentType: ingredient.dataset.ingredienttype
              }))
            });
          }
          
          makeYieldEditable(); // Reattach the click event to #Yield
        } else if ($input.val().trim() === '') {
          // If input is empty on blur, revert to original yield
          $input.replaceWith(`<span id="YieldQuantity">${originalYield}</span>`);
        } else {
          alert("Please enter a valid number greater than 0.");
          $input.focus();
        }
      }
    });
  });
}


// Function to format numbers into fractions or whole numbers
function formatNumber(value) {
	const fractionMap = {
		0.125: '⅛',
		0.25: '¼',
		0.33: '⅓',
		0.5: '½',
		0.66: '⅔',
		0.75: '¾'
	};

	for (let decimal in fractionMap) {
		if (Math.abs(value - decimal) < 0.01) { // Allow small rounding errors
			return fractionMap[decimal];
		}
	}

	if (value % 1 === 0) {
		return value.toString(); // Whole number as integer
	}

	return value.toFixed(1); // Round to one decimal place
}

// Function to convert HTML fractions to numeric values: used in makeYieldEditable()
function parseFraction(value) {
	const fractionMap = {
		'⅛': 0.125,
		'¼': 0.25,
		'⅓': 0.33,
		'½': 0.5,
		'⅔': 0.66,
		'¾': 0.75
	};

	for (let fraction in fractionMap) {
		if (value.includes(fraction)) {
			return parseFloat(value.replace(fraction, fractionMap[fraction]));
		}
	}

	return parseFloat(value); // Parse as float if no fraction is found
}