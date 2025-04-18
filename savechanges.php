<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
include '../../connect.php';

//Prepare a response to return to JavaScript
$response = array(
	'status' => 'success',
	'message' => 'SaveChanges Response: ',
	'data' => null,
	'nutrition' => null,
	'error' => null
);	

function NutrientCalculations($RecipeID) {
    global $mysqli, $response;

    // Start reporting
    $response['message'] .= "NutrientCalculations() called with RecipeID: $RecipeID\n";

    // Query to get all ingredients for the recipe
    $query = "SELECT tblRecipeIngredients.fkComponentID, tblRecipeIngredients.fldQuantity, tblRecipeIngredients.fkUnitID, tblIngredientUnits.fldUnit, tblIngredients.fldIngredientName, tblIngredients.fkUSDAFoodID 
              FROM tblRecipeIngredients
              JOIN tblIngredients on tblRecipeIngredients.fkComponentID=tblIngredients.pkIngredientID
              JOIN tblIngredientUnits on tblRecipeIngredients.fkUnitID=tblIngredientUnits.pkUnitID
              WHERE tblRecipeIngredients.fldType=1 AND tblRecipeIngredients.fkRecipeID=$RecipeID";
    $result = $mysqli->query($query);

    if (!$result) {
        $response['message'] .= "Error fetching ingredients for RecipeID $RecipeID: " . $mysqli->error . "\n";
        return;
    }

    $response['message'] .= "Ingredients fetched successfully for RecipeID $RecipeID.\n";
    $nutrients = [];
    $totalWeight = 0;

    // Process each ingredient
    while ($ingredient = $result->fetch_assoc()) {
        $ingredientID = $ingredient['fkComponentID'];
        $quantity = $ingredient['fldQuantity'];
        $unitID = $ingredient['fkUnitID'];
        $USDAFoodID = $ingredient['fkUSDAFoodID'];

        $response['message'] .= "Processing Ingredient ID $ingredientID (USDAFoodID: $USDAFoodID)  \n";

        // Convert quantity to grams
        $quantityInGrams = ConvertToGrams($quantity, $unitID);
        $response['message'] .= "Converted quantity: $quantity $unitID -> $quantityInGrams grams   \n";

        // Query to get nutrient information for the ingredient
		$nutrientQuery = "SELECT tblFoodNutrient.fldAmount, 
								 tblNutrients.fldNutrientName, 
								 tblNutrients.fldNutrientUnit, 
								 tblNutrients.fldLabelName, 
								 tblNutrients.fldLabelRank
						  FROM tblFoodNutrient
						  JOIN tblNutrients ON tblFoodNutrient.fkNutrientID = tblNutrients.pkNutrientID
						  WHERE tblFoodNutrient.fkFoodID = $USDAFoodID
							AND tblNutrients.fldLabelName IS NOT NULL
							AND tblNutrients.fldLabelName != ''
						  ORDER BY tblNutrients.fldLabelRank ASC";
        $nutrientResult = $mysqli->query($nutrientQuery);

        if (!$nutrientResult) {
            $response['message'] .= "Error fetching nutrients for USDAFoodID $USDAFoodID: " . $mysqli->error . "\n";
            continue;
        }

        $response['message'] .= "Nutrients fetched successfully for USDAFoodID $USDAFoodID.   \n";

        // Process each nutrient
        while ($nutrient = $nutrientResult->fetch_assoc()) {
            $amount = $nutrient['fldAmount'];
            $nutrientName = $nutrient['fldNutrientName'];
            $unit = $nutrient['fldNutrientUnit'];
            $labelName = $nutrient['fldLabelName'];
            $rank = $nutrient['fldLabelRank']; // Retrieve rank here

            // Scale the amount based on the quantity in grams
            $scaledAmount = ($amount / 100) * $quantityInGrams;

            // Sum the nutrient content
            if (!isset($nutrients[$labelName])) {
                $nutrients[$labelName] = [
                    'nutrient' => $labelName,
                    'totalAmount' => 0,
                    'rank' => $rank, // Add rank to the nutrient array
                    'unit' => $unit
                ];
            }
            $nutrients[$labelName]['totalAmount'] += $scaledAmount;
        }

        $totalWeight += $quantityInGrams;
    }

    // Handle recipes-as-ingredients (fldType=2)
    $recipeQuery = "SELECT fkComponentID, fldQuantity, fkUnitID FROM tblRecipeIngredients WHERE fldType=2 AND fkRecipeID=$RecipeID";
    $recipeResult = $mysqli->query($recipeQuery);
    if (!$recipeResult) {
        $response['message'] .= "Error fetching recipes-as-ingredients for RecipeID $RecipeID: " . $mysqli->error . "\n";
        return;
    }

    while ($recipeIngredient = $recipeResult->fetch_assoc()) {
        $componentRecipeID = $recipeIngredient['fkComponentID'];
        $quantity = $recipeIngredient['fldQuantity'];
        $unitID = $recipeIngredient['fkUnitID'];

        $response['message'] .= "Processing Recipe-as-Ingredient ID $componentRecipeID\n";

        // Get the nutrition array from the component recipe
        $nutritionQuery = "SELECT fldNutrition FROM tblRecipes WHERE RecipeID=$componentRecipeID";
        $nutritionResult = $mysqli->query($nutritionQuery);
        if (!$nutritionResult) {
            $response['message'] .= "Error fetching nutrition for RecipeID $componentRecipeID: " . $mysqli->error . "\n";
            continue;
        }

        $componentNutrition = json_decode($nutritionResult->fetch_assoc()['fldNutrition'], true);

        // Convert the quantity to grams
        $quantityInGrams = ConvertToGrams($quantity, $unitID);
        $response['message'] .= "Converted quantity for Recipe-as-Ingredient: $quantity $unitID -> $quantityInGrams grams\n";

        // Process each nutrient in the component recipe
        foreach ($componentNutrition as $nutrient) {
            $labelName = $nutrient['nutrient'];
            $amountPer100g = $nutrient['totalAmount'];
            $scaledAmount = ($amountPer100g / 100) * $quantityInGrams;

            // Sum the nutrient content
            if (!isset($nutrients[$labelName])) {
                $nutrients[$labelName] = [
                    'nutrient' => $labelName,
                    'totalAmount' => 0,
                    'rank' => $nutrient['rank'],
                    'unit' => $nutrient['unit']
                ];
            }
            $nutrients[$labelName]['totalAmount'] += $scaledAmount;
        }

        $totalWeight += $quantityInGrams;
    }

    // Add total weight to response
    $response['message'] .= "Total Weight after processing ingredients: $totalWeight grams\n";
    $response['nutrition']['totalWeight'] = $totalWeight;

    // Add nutrient information to response
    $response['nutrition']['nutrients'] = array_values($nutrients);

    // Update the recipe with the nutrition information
    $nutritionJson = json_encode($response['nutrition']);
    $updateQuery = "UPDATE tblRecipes SET fldNutrition='$nutritionJson' WHERE RecipeID=$RecipeID";
    $mysqli->query($updateQuery);

    if ($mysqli->error) {
        $response['message'] .= "Error updating RecipeID $RecipeID with nutrition information: " . $mysqli->error . "\n";
    } else {
        $response['message'] .= "Nutrition information updated successfully for RecipeID $RecipeID.\n";
    }
}

function ConvertToGrams($quantity, $unitID) {
    switch ($unitID) {
        case 5:  // grams
            return $quantity;
        case 25: // kilograms
            return $quantity * 1000;
        case 6:  // ounces
            return $quantity * 28.3495;
        case 7:  // pounds
            return $quantity * 453.592;
        case 4:  // milliliters
            return $quantity * 1; // Assuming 1g/mL for simplicity
        case 11: // liters
            return $quantity * 1000; // Assuming 1g/mL for simplicity
        case 20: // fluid ounces
            return $quantity * 29.5735; // Assuming 1g/mL for simplicity
        case 10: // cup
            return $quantity * 236.588; // Assuming 1g/mL for simplicity
        case 9:  // tablespoon
            return $quantity * 14.7868; // Assuming 1g/mL for simplicity
        case 8:  // teaspoon
            return $quantity * 4.92892; // Assuming 1g/mL for simplicity
        case 1:  // no unit/produce piece
        case 24: // stick
        case 16: // bag
            return $quantity * 1; // Using 1 as a placeholder
        case 17: // box
            return $quantity * 4;
        case 22: // can
            return $quantity * 2.5;
        case 12: // clove
            return $quantity * 5;
        case 23: // stalk
            return $quantity * 1;
        case 19: // crown
            return $quantity * 2;
        case 18: // head
            return $quantity * 3;
        case 15: // sprig
            return $quantity * 0.03;
        case 14: // bunch
            return $quantity * 2;
        default:
            // Handle other units or throw an error
            throw new Exception("Unknown unitID: $unitID");
    }
}

try{
	if (isset($_POST['Action'])){
		$RecipeID = $_POST['RecipeID'];
		$Action=$_POST['Action'];
			
		/*//////////////////////////////////////////////////////////////////
		/////     UPDATE RECIPE META (Name, Yield, Prep Time, etc)     /////
		//////////////////////////////////////////////////////////////////*/
		if ($_POST['Action'] == "UpdateMeta") {
			$id = $_POST['ID'];
			$newText = ($_POST['NewText'] === '') ? null : $_POST['NewText'];
			$RecipeID = filter_var($_POST['RecipeID'], FILTER_SANITIZE_NUMBER_INT);
		
			$columnMapping = [
				'recipeName' => 'fldRecipeName',
				'prepTime' => 'fldPrepTime',
				'cookTime' => 'fldCookTime',
				'recipeCuisine' => 'fkCuisineID',
				'recipeDescription' => 'fldDescription',
				'Yield' => 'fldYield',
				'YieldUnit' => 'fldYieldUnit'
			];
		
			$column = $columnMapping[$id] ?? null;
		
			if ($column) {
				$SQL = "UPDATE tblRecipes SET $column = ? WHERE RecipeID = ?";
				$STMT = $mysqli->prepare($SQL);
		
				if (!$STMT) {
					error_log("Prepare failed: " . $mysqli->error);
					$response['status'] = 'error';
					$response['message'] = 'Prepare failed: ' . $mysqli->error;
				} else {
					$paramType = ($column === 'fkCuisineID') ? 'ii' : 'si';
					$STMT->bind_param($paramType, $newText, $RecipeID);
		
					if (!$STMT->execute()) {
						error_log("Execute failed: " . $STMT->error);
						$response['status'] = 'error';
						$response['message'] = 'Execute failed: ' . $STMT->error;
					} else {
						$response['status'] = 'success';
						$response['message'] = 'Changes to '.$column.' saved successfully.';
						if ($column === 'fkCuisineID') {
							error_log("Cuisine update successful");
						}
					}
		
					$STMT->close();
				}
			} else {
				$response['status'] = 'error';
				$response['message'] = 'Invalid column mapping.';
			}
		}	
		
		/*///////////////////////////////
		/////     IMAGE UPLOADS     /////
		///////////////////////////////*/
		if ($_POST['Action'] == 'UpdateImage') {
			 $response['message'] = 'SaveChanges:UpdateImage.';
			if (isset($_FILES['image'])) {
				$file = $_FILES['image'];
				$fileName = $file['name'];
				$fileType = $file['type'];
				$fileTmp = $file['tmp_name'];
	
				// Verify it's an image
				if (strpos($fileType, 'image/') === 0) {
					// Generate unique filename
					$extension = pathinfo($fileName, PATHINFO_EXTENSION);
					$newFileName = 'recipe_' . $RecipeID . '_' . time() . '.' . $extension;
	
					// Move file to images directory
					if (move_uploaded_file($fileTmp, '../../images/recipes/' . $newFileName)) {
						// Update database with new filename
						$SQL = "UPDATE tblRecipes SET fldRecipeImage = ? WHERE RecipeID = ?";
						$STMT = $mysqli->prepare($SQL);
						$STMT->bind_param("si", $newFileName, $RecipeID);
	
						if ($STMT->execute()) {
							$response['status'] = 'success';
							$response['message'].= 'Image updated successfully.';
							$response['data'] = array('filename' => $newFileName);
						} else {
							$response['status'] = 'error';
							$response['message'].= 'Database update failed.';
						}
					} else {
						$response['status'] = 'error';
						$response['message'].= 'File upload failed.';
					}
				} else {
					$response['status'] = 'error';
					$response['message'].= 'Invalid file type.';
				}
			} else {
				$response['status'] = 'error';
				$response['message'].= 'No image file uploaded.';
			}
		}
	
	
		/*///////////////////////////////////////////////////
		/////     ADD AND UPDATE RECIPE INGREDIENTS     /////
		///////////////////////////////////////////////////*/
		if($_POST['Action'] == 'InsertIngredient' || $_POST['Action'] == 'UpdateIngredient'){
			$response['message'].=" | ".$Action;
			
			//All row types			
			$Type=$_POST['Component']; $response['message'].="\n Component Type: ".$Type;
			
			//Regular Ingredient or Recipe-as-ingredient
			if($Type==1||$Type==2){
				$Quantity = $_POST['Quantity']; $response['message'].="\nQuantity: ".$Quantity;
				$UnitID = $_POST['UnitID']; $response['message'].="\n UnitID: ".$UnitID;
				$IngredientID = $_POST['IngredientID']; $response['message'].="\nIngredientID: ".$IngredientID;
			}

			//All row types
			$Order = $_POST['Order']; $response['message'].="\nOrder: ".$Order;
			$PrepNotes = $_POST['PrepNotes']; $response['message'].="\nPrepNotes: ".$PrepNotes;

			//Updates
			$RecipeIngredientID=$_POST['RecipeIngredientID']; $response['message'].="\n Existing Row IngredientID: ".$RecipeIngredientID;


			// Check required fields for regular ingredients and recipe-as-ingredient
			if(($Type==1||$Type==2) && ( empty($Quantity) || empty($UnitID) || empty($IngredientID))){
				$response['message'].= 'Failed to insert ingredient. Missing Quantity, Unit, or IngredientID';
				$response['status']='failed'; 
				exit;
			}
			
			// Insert ingredient
			if($_POST['Action'] == 'InsertIngredient'){
				$SQL = "INSERT INTO tblRecipeIngredients (fkRecipeID, fldType, fkComponentID, fldIngredientOrder, fldQuantity, fkUnitID, fldPrepNotes) VALUES (?, ?, ?, ?, ?, ?, ?)";
				$STMT = $mysqli->prepare($SQL);
				$STMT->bind_param("iiiidis", $RecipeID, $Type, $IngredientID, $Order, $Quantity, $UnitID, $PrepNotes);
				if($STMT->execute()){
					$response['message'].= 'Ingredient inserted successfully';
					$response['data']= $mysqli->insert_id; // Return the new pkRecipeIngredientID
					NutrientCalculations($RecipeID);			
				}
				else{
					$response['status']='failed';
					$response['message'].=" FAILED to execute: ".$SQL;	
				}
	
			}
			
			//Update ingredient
			if($_POST['Action'] == 'UpdateIngredient'){
			
						
				 $SQL = "UPDATE tblRecipeIngredients 
						SET fkComponentID = ?, 
							fldIngredientOrder = ?, 
							fldQuantity = ?, 
							fkUnitID = ?, 
							fldPrepNotes = ? 
						WHERE pkRecipeIngredientID = ?";
		
				// Prepare and bind the statement
				$STMT = $mysqli->prepare($SQL);
				$STMT->bind_param("iidisi", $IngredientID, $Order, $Quantity, $UnitID, $PrepNotes, $RecipeIngredientID);
				if ($STMT->execute()) {
					$response['message'].= 'Ingredient updated successfully';
					NutrientCalculations($RecipeID);
				} 
				else {
					$response['status']='failed';
					$response['message'].=" FAILED to execute: ".$SQL;	
				}	
				$STMT->close();
			}//if($_POST['Action'] == 'UpdateIngredient')
			
		}//if($_POST['Action'] == 'InsertIngredient' || $_POST['Action'] == 'UpdateIngredient')
		
			
		/*/////////////////////////////////////////////////////////////////
		/////     ADD AND UPDATE EDITABLE ROWS (INSTRUCTIONS/TIPS)     /////
		//////////////////////////////////////////////////////////////////*/
		if ($_POST['Action'] == 'Insert' || $_POST['Action'] == 'Update') {
			$Text = $_POST['Text'];
			$ListOrder = $_POST['ListOrder'];
			$RecipeID = $_POST['RecipeID'];
		
			if ($_POST['RecipeComponent'] == "instruction") {
				$Table = "tblRecipeInstructions";
				$UniqueID = "pkInstructionID";
				$ListField = "fldStepNumber";
				$TextField = "fldInstructions";
			} elseif ($_POST['RecipeComponent'] == "tip") {
				$Table = "tblRecipeTips";
				$UniqueID = "pkRecipeTipID";
				$ListField = "fldTipOrder";
				$TextField = "fldRecipeTip";
			}
		
			if ($_POST['Action'] === 'Insert') {
				$SQL = "INSERT INTO " . $Table . " (" . $TextField . ", " . $ListField . ", fkRecipeID) VALUES (?, ?, ?)";
				$stmt = $mysqli->prepare($SQL);
				$stmt->bind_param("sii", $Text, $ListOrder, $RecipeID);
				
				if ($stmt->execute()) {
					$response['data'] = $mysqli->insert_id;
					$response['message'] .= "Record inserted successfully.";
				} else {
					$response['status'] = 'error';
					$response['message'] .= "Error inserting record: " . $mysqli->error;
				}
			}
		
			if ($_POST['Action'] === 'Update') {
				$SQL = "UPDATE " . $Table . " SET " . $TextField . " = ?, " . $ListField . " = ? WHERE " . $UniqueID . " = ? AND fkRecipeID = ?";
				$stmt = $mysqli->prepare($SQL);
				$stmt->bind_param("siii", $Text, $ListOrder, $_POST['UniqueID'], $RecipeID);
				
				if ($stmt->execute()) {
					$response['message'] .= $_POST['Action']." ".$Table." successful";
				} else {
					$response['status'] = 'error';
					$response['message'] .= "Error updating. ".$Table. $mysqli->error;
				}
			}
		
			$stmt->close();
		}
		
		
		/*/////////////////////////////////////////////////////////////////////
		/////     RE-SORT ROW ORDERS (Ingredients, Instructions, Tips)    /////
		/////////////////////////////////////////////////////////////////////*/
		if ($_POST['Action'] == 'Sort') {
			$RecipeComponent = $_POST['RecipeComponent'];
			$UpdatedOrder = json_decode($_POST['UpdatedOrder'], true);
		
			switch ($RecipeComponent) {
				case 'recipeingredient':
					$Table = "tblRecipeIngredients";
					$UniqueID = "pkRecipeIngredientID";
					$ListOrder = "fldIngredientOrder";
					break;
				case 'instruction':
					$Table = "tblRecipeInstructions";
					$UniqueID = "pkInstructionID";
					$ListOrder = "fldStepNumber";
					break;
				case 'tip':
					$Table = "tblRecipeTips";
					$UniqueID = "pkRecipeTipID";
					$ListOrder = "fldTipOrder";
					break;
				default:
					$response['status'] = 'error';
					$response['message'] .= 'Invalid RecipeComponent';
					return;
			}
		
			$Updates = [];
			$Values = [];
		
			foreach ($UpdatedOrder as $Item) {
				$Updates[] = "WHEN $UniqueID = ? THEN ?";
				$Values[] = $Item['uniqueId'];
				$Values[] = $Item['listOrder'];
			}
		
			$UniqueIds = array_column($UpdatedOrder, 'uniqueId');
			$Placeholders = implode(',', array_fill(0, count($UniqueIds), '?'));
		
			$SQL = "UPDATE $Table 
					SET $ListOrder = CASE " . implode(' ', $Updates) . " END
					WHERE $UniqueID IN ($Placeholders) AND fkRecipeID = ?";
		
			$stmt = $mysqli->prepare($SQL);
		
			if ($stmt) {
				$Values = array_merge($Values, $UniqueIds, [$RecipeID]);
				$Types = str_repeat('i', count($Values)); // Assuming all values are integers
		
				$stmt->bind_param($Types, ...$Values);
				$Result = $stmt->execute();
		
				if ($Result) {
					$response['message'] .= 'Order updated successfully';
				} else {
					$response['status'] = 'error';
					$response['message'] .= 'Failed to update order: ' . $mysqli->error;
				}
		
				$stmt->close();
			} else {
				$response['status'] = 'error';
				$response['message'] .= 'Failed to prepare statement: ' . $mysqli->error;
			}
		}
	
	
		/*/////////////////////////////////////////////////////////
		/////     DELETE INGREDIENTS, INSTRUCTIONS & TIPS     /////
		/////////////////////////////////////////////////////////*/
		if ($_POST['Action'] === 'Delete') {
			$RecipeComponent = $_POST['RecipeComponent'];
			$UniqueID = $_POST['UniqueID'];
		
			if (empty($RecipeComponent) || empty($UniqueID)) {
				$response['message'] = "Failed to delete {$RecipeComponent} component with ID of {$UniqueID}";
				$response['status'] = 'failed';
				echo json_encode($response);
				exit;
			}
		
			switch ($RecipeComponent) {
				case 'recipeingredient':
					$Table = 'tblRecipeIngredients';
					$PrimaryKey = 'pkRecipeIngredientID';
					break;
				case 'instruction':
					$Table = 'tblRecipeInstructions';
					$PrimaryKey = 'pkInstructionID';
					break;
				case 'tip':
					$Table = 'tblRecipeTips';
					$PrimaryKey = 'pkRecipeTipID';
					break;
				default:
					$response['message'] = 'Invalid Recipe Component';
					$response['status'] = 'failed';
					echo json_encode($response);
					exit;
			}
		
			$SQL = "DELETE FROM {$Table} WHERE {$PrimaryKey} = ?";
			$STMT = $mysqli->prepare($SQL);
			$STMT->bind_param('i', $UniqueID);
		
			if ($STMT->execute()) {
				$response['message'] = strtoupper($RecipeComponent) . ' deleted successfully';
				$response['status'] = 'success';
				if($RecipeComponent=="recipeingredient"){NutrientCalculations($RecipeID);}//Recalculate nutrient information.
			} else {
				$response['message'] = "Failed to delete {$RecipeComponent}";
				$response['status'] = 'failed';
			}
		}//if$_POST['Action']== 'Delete'])
	
	
		/*/////////////////////////////
		/////     MANAGE TAGS     /////
		/////////////////////////////*/
		if ($_POST['Action'] == 'UpdateTag') {
			
			$RecipeID = $_POST['RecipeID'];
			$TypeID = $_POST['TypeID'];
			$IsActive = filter_var($_POST['IsActive'], FILTER_VALIDATE_BOOLEAN);
			
			if ($IsActive) {$SQL = "INSERT INTO tblRecipeTags (fkRecipeID, fkTagID) VALUES (?, ?)";} 
			else {$SQL = "DELETE FROM tblRecipeTags WHERE fkRecipeID = ? AND fkTagID = ?";}
			
			$STMT = $mysqli->prepare($SQL);
			if (!$STMT) {
				error_log("Prepare failed: " . $mysqli->error);
				echo json_encode(['success' => false, 'error' => 'Prepare failed']);
				exit;
			}
			
			$STMT->bind_param("ii", $RecipeID, $TypeID);
			
			if ($STMT->execute()) {
				echo json_encode(['success' => true, 'message' => 'Tag updated']);
			} else {
				error_log("Execute failed: " . $STMT->error);
				echo json_encode(['success' => false, 'error' => 'Execute failed']);
			}
			exit;
		}
	
		
		/*/////////////////////////////////////
		/////     JAVASCRIPT RESPONSE     /////
		/////////////////////////////////////*/
		header('Content-Type: application/json');
		echo json_encode($response);
	}//if(isset($_POST['Action'])
}
catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] .= 'An error occurred';
    $response['error'] = array(
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    );
}
?>