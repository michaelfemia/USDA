$(document).ready(function() {
	
	/*/////////////////////////////////////////////////////////
	/////     IMAGE & META INFO (title, cuisine, etc)     /////
	/////////////////////////////////////////////////////////*/
	ImageUploadHandler();//Handle image changes 
	CuisineChangeHandler(); //Listen for changes to cuisine <select>
   
	//Fields that should blur on press of return and initiate a save
    InitializeEditableFields('input#recipeName, input#prepTime, input#cookTime, textarea#recipeDescription,input#Yield,input#YieldUnit');
	
	//Display the character count of the recipe description (limited to 250 characters)
	$('#charCount').text($('#recipeDescription').val().length + '/250');
	CharacterCountWarning('#recipeDescription', '#charCount', 250, 200, 249);//Change color of counter text 

	/*////////////////////////////////////////////////// 
	/////     Ingredients, Instructions & Tips     /////
	//////////////////////////////////////////////////*/
	AddRecipeComponent();//Listener to add new ingredients, instructions and tips.
	
	//Ingredients
	InitializeSaveIngredientHandlers();//Handle updates
	InitializeIngredientAutocomplete('.IngredientRow input[type="text"].IngredientName');
	ToggleIngredientUnits();//toggle between singular and plural measuring units

	//Instructions & Tips
	
	UpdateEditableRows();//save new values

	//Delete function for ingredients, instructions and tips
	DeleteRecipeItem();

	//Drag and drop sorting
	DragAndDropSorting();
	
	
	/*//////////////////////
	/////     TAGS     /////
	//////////////////////*/
	ToggleTags(); //Listen for tag changes
	
	//Apply CSS active class for tags in use and sort the list accordingly
	activeTags.forEach(function(fkTagID) {$('#type_' + fkTagID).addClass('active');});
	SortTags(); //Move tags in use to the front of the list



	/*/////////////////////////
	/////     UTILITY     /////
	/////////////////////////*/

	//Force convert any rich text on the clipboard from other applications to plaintext
	$('input[type="text"], [contenteditable="true"]').on('paste', HandlePaste);
	
	//If a field is editable, pressing return does not create a new line, it initiates blur+save
	$(document).on('keypress', '[contenteditable="true"]', function(e) {
		if (e.which === 13) { // Enter key
			e.preventDefault();
			$(this).blur();
			return false;
		}
	});
		
});//$(document).ready


function NutritionFacts() {
    // Debugging info
    var Debug = "NutritionFacts(): Processing nutrition information.\n";

    try {
        // Ensure the response and response.nutrition exist
        if (response && response.nutrition) {
            Debug += "Nutrition information successfully accessed.\n";

            // Access the nutrition array
            var nutritionData = response.nutrition;

            // Log the nutrition data for testing purposes
            console.log("Nutrition Data:", nutritionData);

            // Optional: Display the nutrition data in the UI
            DisplayNutritionData(nutritionData);
        } else {
            Debug += "Error: Nutrition information is missing in the response.\n";
            console.error("NutritionFacts Error: Response or nutrition data is undefined.");
        }
    } catch (error) {
        Debug += "Error encountered while processing nutrition data.\n";
        console.error("NutritionFacts Exception:", error);
    } finally {
        // Debugging info
        console.log(Debug);
    }
}

function DragAndDropSorting() {
  const containers = document.querySelectorAll('.DataRowContainer');
  
  containers.forEach(container => {
    Sortable.create(container, {
      animation: 150,
      handle: '.DragHandle',
      onEnd: function(evt) {
        UpdateRowOrder(container);
      }
    });
  });
}


function UpdateRowOrder(container) {
  const rows = container.querySelectorAll('.DataRow');
  const updatedOrder = [];
  let recipeComponent = '';

  rows.forEach((row, index) => {
    const listOrder = index + 1;
    const uniqueId = row.dataset.uniqueid;
    const rowListOrder = row.querySelector('.RowListOrder');

    rowListOrder.value = listOrder;
    updatedOrder.push({ uniqueId: uniqueId, listOrder: listOrder });

    if (index === 0) {
      recipeComponent = row.dataset.recipeComponent;
    }
  });

  console.log('Updated order:', updatedOrder);
  console.log('Recipe component:', recipeComponent);

  // Send the updated order to the server
  var Action="Sort";
  SaveChange({
    Action: Action,
    RecipeComponent: recipeComponent,
    UpdatedOrder: JSON.stringify(updatedOrder)
  })
    .done(function(response) {
      console.log('Order saved successfully:', response);
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
      console.error('Error saving order:', textStatus, errorThrown);
    });
}


//Listener for all addition buttons (ingredient, instruction, tip)
function AddRecipeComponent() {
  document.addEventListener('click', function(event) {
	if (event.target.tagName.toLowerCase() === 'i' && event.target.classList.contains('AddComponent')) {
		var NewRecipeComponent = event.target.getAttribute('data-recipe-component');
		var NewOrder = event.target.getAttribute('data-order');
		switch (NewRecipeComponent) {
			case 'ingredient':
				var IngredientComponent = document.querySelector('select#IngredientComponentType').value;
				AddIngredient(NewOrder,IngredientComponent);
				document.querySelector('select#IngredientComponentType').value = "1";
				break;
			case 'instruction':
			case 'tip':
				AddEditableRow(NewRecipeComponent, NewOrder);
				break;
			default:
				console.log('Unknown component type:', NewRecipeComponent);
		}
      DeleteRecipeItem();//listeners for delete buttons
      DragAndDropSorting();//drag and drop sorting
    }
  });
}

//Add new ingredient rows
function AddIngredient(NewOrder,Component) {
	var Debug = "AddIngredient()\n";
	var Order = NewOrder;
	console.log("Add ingredient component: "+Component);
	var html = "<div class='NewIngredientRow IngredientRow DataRow' data-recipe-component='recipeingredient' data-subtype='"+Component+"' data-uniqueid=''>";
	html += DragHandle;
	if (Component == '1' || Component == '2'){
		console.log("allegedly ingredient or recipe");
		html += "<input type='number' class='IngredientQuantity' required>";
		html += "<select class='IngredientUnitMenu' required>";
		html += "<option selected value='1'>---</option>";
		
		// Add optgroups for each category using UnitsArray
		for (var category in UnitsArray) {
			if (UnitsArray.hasOwnProperty(category) && UnitsArray[category].length > 0) {
				html += "<optgroup label='" + category + "'>";
				UnitsArray[category].forEach(function(Unit) {
					html += "<option value='" + Unit.UnitID + "' data-singular='" + Unit.Singular + "' data-plural='" + Unit.Plural + "'>" + Unit.Singular + "</option>";
				});
				html += "</optgroup>";
			}
		}
		
		html += "</select>";    
		html += "<input type='text' class='IngredientName' class='ingredient-input ingredient-autocomplete' required>";

	}
	var PrepNotesClass="IngredientPrepNotes";
	if (Component == '3'){PrepNotesClass+=" IngredientSubheader";}
	html += "<input type='text' class='"+PrepNotesClass+"' class='ingredient-input'>";
	html += "<input type='hidden' class='RowListOrder' value='" + Order + "'>";
	html += DeleteButton;
	html += "</div>";
	
	$(html).insertBefore('#IngredientsList .AddRow');
	
	if (Component == '1' || Component == '2'){
		// Initialize autocomplete for the new ingredient row
		InitializeIngredientAutocomplete('.NewIngredientRow input[type="text"].IngredientName');
	
		//Adjust ingredient units between plural and singular based on quantity
		ToggleIngredientUnits();
		
	
		// Focus on quantity field after adding new row
		$('#NewIngredientQuantity').focus(); //FOCUS BY CLASS
	}
	
}

//Add new instructions and tips
function AddEditableRow(ComponentType, NewOrder) {
    var Debug = `AddEditableRow(${ComponentType}, ${NewOrder})`;
    var Order = NewOrder;
    var ContainerId = ComponentType === 'instruction' ? 'InstructionsList' : 
                      ComponentType === 'tip' ? 'TipsList' : 
                      null;

    // Error checking
    if (ContainerId === null) {
        console.error(`Invalid ComponentType: ${ComponentType}`);
        return; // Exit the function if an invalid type is provided
    }

    var Html = `<div class='NewDataRow DataRow' data-recipe-component='${ComponentType}' data-uniqueid=''>`;
    Html += DragHandle;
    Html += `<p class='EditableRow' contenteditable='true'></p>`;
    Html += `<input type='hidden' class='RowListOrder' value='${Order}'>`;
    Html += DeleteButton;
    Html += '</div>';
    
    $(`#${ContainerId} .AddRow`).before(Html);
    var $NewRow = $(`#${ContainerId} .DataRow:last`);
    var $NewParagraph = $NewRow.find('p.EditableRow');
    
    $NewParagraph.on('paste', HandlePaste);
    $NewParagraph.focus();
    
    //SetupEditableRows();
	UpdateEditableRows();
    
    console.log(Debug);
}


function UpdateEditableRows() {
  document.querySelectorAll('div.DataRow p.EditableRow[contenteditable="true"]').forEach(Row => {
    Row.addEventListener('blur', function() {
      const DataRow = this.closest('.DataRow');
      const RecipeComponent = DataRow.getAttribute('data-recipe-component');
      let Text = this.textContent;
      const ListOrder = DataRow.querySelector('input.RowListOrder').value;

      // Trim trailing whitespace
      Text = Text.trimEnd();

      // Add a period if the last character is not a period, closing parenthesis, or exclamation point
      if (!['.', ')', '!'].includes(Text.slice(-1))) {
        Text += '.';
      }

      // Replace "saute" with "sauté" and "puree" with "purée"
      Text = Text.replace(/saute/gi, 'sauté').replace(/puree/gi, 'purée');

      // Capitalize the first letter of the first word
      if (Text) {
        Text = Text.charAt(0).toUpperCase() + Text.slice(1);
      }

      // Update the text on the page if changes were made
      if (this.textContent !== Text) {
        this.textContent = Text;
      }

      let Action = "Update";
      let UniqueID;

      if (DataRow.classList.contains('NewDataRow')) {
        Action = "Insert";
      } else {
        UniqueID = DataRow.getAttribute('data-uniqueid');
      }

      // Prepare data object for AJAX call
      const data = {
        RecipeComponent: RecipeComponent,
        Text: Text,
        ListOrder: ListOrder,
        Action: Action
      };

      // Only include UniqueID if it's set
      if (UniqueID) {
        data.UniqueID = UniqueID;
      }

      // Make AJAX call using SaveChange function
      SaveChange(data)
        .done(function(response) {
          console.log('Save successful:', response);
          if (Action === "Insert") {
            DataRow.classList.remove('NewDataRow');
            if (response) {
              var NewID = JSON.parse(response).data;
              DataRow.setAttribute('data-uniqueid', NewID);
            }
          }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
          console.error('Save failed:', textStatus, errorThrown);
        });

      console.log('UpdateEditableRows()');
      console.log('RecipeComponent:', RecipeComponent);
      console.log('Action:', Action);
      console.log('Text:', Text);
      console.log('ListOrder:', ListOrder);
      if (UniqueID) console.log('UniqueID:', UniqueID);
    });
  });
}

//Delete tips, instructions, and ingredient rows
function DeleteRecipeItem() {
  $('.DataRow button.Delete').on('click', function() {
    var $DataRow = $(this).closest('.DataRow');
    var RecipeComponent = $DataRow.data('recipe-component');
    var UniqueID = $DataRow.data('uniqueid');

    console.log('Deleting ' + RecipeComponent + ' with ID:', UniqueID);
    
    // Prepare data for AJAX call
    var DeleteData = {
      Action: 'Delete',
      RecipeComponent: RecipeComponent,
      UniqueID: UniqueID
    };

    // Use the custom SaveChange function for AJAX call
    SaveChange(DeleteData)
      .done(function(response) {
        console.log('Delete successful:', response);
        $DataRow.remove(); // Remove the row from the DOM

        //NutritionFacts() to recalculate nutrient info when ingredients are deleted
        if (RecipeComponent === "recipeingredient") {
          NutritionFacts();
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Delete failed:', textStatus, errorThrown);
        // Handle error (e.g., show an error message to the user)
      });
  });
}

function ImageUploadHandler() {
    let isUploading = false;

    // Recipe Image Upload Handler
    $('.change-image-btn').on('click', function() {
        $('#recipeImageUpload').click();
    });

    $('#recipeImageUpload').on('change', function() {
        console.log('File selected:', this.files[0]);
        
        // First, create a preview of the uploaded file
        var file = this.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            // Update image preview immediately
            $('.recipe-image').attr('src', e.target.result);
            
            // Then proceed with the upload
            var formData = new FormData();
            formData.append('Action', 'UpdateImage');
            formData.append('RecipeID', RecipeIDVariable);
            formData.append('image', file);
            
            console.log('FormData contents:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ':', pair[1]);
            }

            isUploading = true;

            $.ajax({
                url: '/editor/ajax/savechanges.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Server response:', response);
                    try {
                        var parsedResponse = JSON.parse(response);
                        if (parsedResponse.status === 'success') {
                            console.log('Upload successful:', parsedResponse.message);
                        } else {
                            console.error('Upload failed:', parsedResponse.message || 'Unknown error');
                        }
                    } catch (e) {
                        console.error('Failed to parse server response:', e);
                    }
                    isUploading = false;
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.log('Response Text:', xhr.responseText);
                    isUploading = false;
                }
            });
        };
        
        reader.readAsDataURL(file);
    });

    // Add event listener for page unload
    window.addEventListener('beforeunload', function (e) {
        if (isUploading) {
            // Cancel the event
            e.preventDefault();
            // Chrome requires returnValue to be set
            e.returnValue = 'Image upload still in progress';
        }
    });
}


//Insert and update ingredients
function InitializeSaveIngredientHandlers() {
    $(document).on('blur', '.IngredientRow', function (e) {
        var Debug= "InitializeSaveIngredientHandlers()\n";
        var $row = $(this).closest('.IngredientRow');
        var isNewIngredient = $row.hasClass('NewIngredientRow');
        var Action = isNewIngredient ? "InsertIngredient" : "UpdateIngredient";
        var Component = parseInt($row.data('subtype'), 10);
                
        //Updates
        var RecipeIngredientID="";
        if (Action === "UpdateIngredient") {
            RecipeIngredientID=$row.data('uniqueid');
        }
        
        if(Component === 1 || Component === 2){
            var Quantity = parseFloat($row.find('input.IngredientQuantity').val().trim());
            var Unit = parseInt($row.find('select.IngredientUnitMenu').val(), 10);
            if (isNaN(Unit) || Unit === null || Unit === undefined){Unit = 0;}// for cases like "1 apple" etc.
            var IngredientID = parseInt($row.find('input.IngredientName').attr('data-ingredient-id'), 10);
        }
        
        var PrepNotes = $row.find('input.IngredientPrepNotes').val();
        var Order = parseInt($row.find('input.RowListOrder:hidden').val(), 10);
        
        // Check if Quantity, Unit, and IngredientID are set
        if ( ((Component === 1 || Component === 2) && (Quantity && IngredientID)) || (Component === 3 && PrepNotes) ){
            Debug+=Action+"\n";    
            SaveChange({
            Action: Action,
            Component: Component,
            Quantity: Quantity,
            UnitID: Unit,
            IngredientID: IngredientID,
            PrepNotes: PrepNotes,
            Order: Order,
            RecipeIngredientID: RecipeIngredientID
            }).done(function(response) {
                //var result = JSON.parse(response);
                if (response.status === 'success') {
                    Debug+="\n Javascript received successful query report \n";
                    if (Action === "InsertIngredient") {
                        DragAndDropSorting();
                                                
                        //The new row doesn't have a RecipeIngredientID yet
                        var NewRecipeIngredientID= response.data;
                        $row.attr('data-uniqueid', NewRecipeIngredientID);
                        
                        //Remove the NewIngredientRow class
                        $row.removeClass('NewIngredientRow');
                    }

                    // Call NutritionFacts() if Component is 1 or 2
                    if (Component === 1 || Component === 2) {
                        NutritionFacts();
                    }
                }
            }).fail(function(xhr, status, error) {
                console.error('JS: Save failed:', error);
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            console.error('PHP Error:', response.error);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', xhr.responseText);
                    }
                }
            });

        } else {
            Debug+="InitializeSaveIngredientHandlers(): Quantity, Unit, or IngredientID is not set. Save not executed.\n\n";
            Debug+="RecipeIngredientID: "+RecipeIngredientID+"\n Action: "+Action+"\n Component: "+Component+"\n Quantity: "+Quantity+"\n Unit: "+Unit+"\n IngredientID: "+IngredientID+"\n PrepNotes: "+PrepNotes+"\n Order: "+Order;
        }
        //console.log(Debug);
    });
}

function InitializeIngredientAutocomplete(selector) {
    $(selector).each(function () {
        var $input = $(this);
        var $parentRow = $input.closest('.DataRow');
        var ComponentType = parseInt($parentRow.data('subtype'), 10); // Ensure ComponentType is an integer

        if (isNaN(ComponentType)) {
            console.error("ComponentType is not set or invalid for row:", $parentRow);
            return; // Skip this input if ComponentType is invalid
        }

        $input.autocomplete({
            source: function (request, response) {
                var term = request.term.trim().toLowerCase();
                var array = ComponentType === 1 ? IngredientsArray : RecipeNamesArray;
                var nameKey = ComponentType === 1 ? 'IngredientName' : 'RecipeName';
                var idKey = ComponentType === 1 ? 'IngredientID' : 'RecipeID';

                // Filter matches
                var startsWithMatches = [];
                var containsMatches = [];
                $.each(array, function (i, item) {
                    var name = item[nameKey].toLowerCase();
                    if (name.indexOf(term) === 0) {
                        startsWithMatches.push(item);
                    } else if (name.indexOf(term) > 0) {
                        containsMatches.push(item);
                    }
                });

                // Combine and respond with matches
                response(startsWithMatches.concat(containsMatches).slice(0, 10));
            },
            minLength: 1,
            select: function (event, ui) {
                //console.log("Selected item:", ui.item);

                // Update input value and data-ingredient-id
                var nameKey = ComponentType === 1 ? 'IngredientName' : 'RecipeName';
                var idKey = ComponentType === 1 ? 'IngredientID' : 'RecipeID';
                $input.val(ui.item[nameKey]);
                $input.attr('data-ingredient-id', ui.item[idKey]); // Always update data-ingredient-id

                return false; // Prevent default behavior
            }
        }).each(function () {
            $(this).data('ui-autocomplete')._renderItem = function (ul, item) {
                var nameKey = ComponentType === 1 ? 'IngredientName' : 'RecipeName';
                return $("<li>")
                    .append($("<div>").text(item[nameKey]))
                    .appendTo(ul);
            };
        });

        // Debugging to confirm initialization
        //console.log("Autocomplete initialized for input:", $input, "with ComponentType:", ComponentType);
    });
}

//Choose Plural or Singular ingredient units
function ToggleIngredientUnits() {
  const IngredientsListDiv = document.getElementById('IngredientsList');
  if (!IngredientsListDiv) return;

  const Rows = IngredientsListDiv.querySelectorAll('.IngredientRow');

  Rows.forEach(Row => {
    const QuantityInput = Row.querySelector('.IngredientQuantity');
    const UnitMenu = Row.querySelector('.IngredientUnitMenu');

    if (QuantityInput && UnitMenu) {
      QuantityInput.addEventListener('input', function() {
        const Quantity = parseFloat(this.value) || 0;
        const Options = UnitMenu.querySelectorAll('option');

        Options.forEach(Option => {
          const Singular = Option.getAttribute('data-singular');
          const Plural = Option.getAttribute('data-plural');
          
          if (Singular && Plural) {
            Option.textContent = Quantity <= 1 ? Singular : Plural;
          }
        });
      });
    }
  });
}

//AJAX call function
function SaveChange(data) {
    // Ensure recipeID is always included
    data.RecipeID = RecipeIDVariable;

    return $.ajax({
        type: 'POST',
        url: '/editor/ajax/savechanges.php',
        data: data
    }).done(function(response) {
        // Access different elements of the response
        console.log("Status: " + response.status);
        console.log("Message: " + response.message);
        console.log("Data: " + JSON.stringify(response.data)); 
    });
}

function InitializeEditableFields(selector) {
    $(selector).on('keypress blur', function(e) {
        if (e.type === 'blur' || e.which === 13) {
            e.preventDefault();
            
            var $this = $(this);
            var NewValue = $this.val() !== undefined ? $this.val() : $this.text();
            NewValue = NewValue.trim(); // Trim whitespace
            var ID = $this.attr('id');
            console.log("InitializeEditableFields() tried to update "+ID+" with '"+NewValue+"'");
            
            // If enter was pressed, remove focus
            if (e.which === 13) {
                $this.blur();
            }
            
            SaveChange({
                Action: 'UpdateMeta',
                ID: ID,
                NewText: NewValue
            });
        }
    });
}


//Change cuisine
function CuisineChangeHandler() {
    $('#recipeCuisine').on('change', function() {
        var $this = $(this);
        var newValue = $this.val();
        var id = $this.attr('id');
        
        SaveChange({
            Action:'UpdateMeta',
            ID: id,
            NewText: newValue
        }).done(function() {
            $this.blur(); // Remove focus after successful update
        });
    });
}


//Select or deselect tags
function ToggleTags() {
    $(document).on('click', '.recipe-tags span', function() {
        var $this = $(this);
        var TypeID = $this.attr('id').split('_')[1];
        var IsActive = $this.hasClass('active');

        $this.toggleClass('active');
        SortTags();
        
        SaveChange({
            Action: 'UpdateTag',
            TypeID: TypeID,
            IsActive: !IsActive
        }).done(function(response) {
            if (!IsActive) {
                activeTags.push(typeID);
            } else {
                activeTags = activeTags.filter(tag => tag != TypeID);
            }
        }).fail(function() {
            $this.toggleClass('active');
            SortTags();
        });
    });
}


//Move actively selected recipe tags to the front of the tag list
function SortTags() {
	var $tagContainer = $('.recipe-tags');
	var $tags = $tagContainer.find('span').get();
	
	$tags.sort(function(a, b) {
		var aActive = $(a).hasClass('active');
		var bActive = $(b).hasClass('active');
		
		if (aActive && !bActive) return -1;
		if (!aActive && bActive) return 1;
		return $(a).text().localeCompare($(b).text());
	});
	
	$.each($tags, function(idx, tag) {
		$tagContainer.append(tag);
	});
}


//Warn if input is approaching the character count limit
function CharacterCountWarning(inputField, countField, maxChars, threshold1, threshold2) {
    $(inputField).on('input', function() {
        var currentLength = $(this).val().length;
        $(countField).text(currentLength + '/' + maxChars);
        
        if (currentLength > threshold2) {
            $(countField).css('color', 'red');
        } else if (currentLength > threshold1) {
            $(countField).css('color', 'orange');
        } else {
            $(countField).css('color', '');
        }
    });
}


//Only allow plain text pasting
function HandlePaste(e) {
	e.preventDefault();
	var text = (e.originalEvent || e).clipboardData.getData('text/plain');
	document.execCommand('insertText', false, text);
}


// Format number to remove unnecessary decimal places in ingredient quantities (from the database)
function FormatQuantity($number) {
	// Convert to float first to handle string inputs
	$num = floatval($number);
	// If it's a whole number, return as integer
	if ($num == floor($num)) {
		return number_format($num, 0);
	}
	// Otherwise return with up to 2 decimal places, trimming unnecessary zeros
	return rtrim(rtrim(number_format($num, 2), '0'), '.');
}