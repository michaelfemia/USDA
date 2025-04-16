<?
function NutrientCalculations() {
    global $RecipeID, $mysqli, $response;

    // Query to get all ingredients for the recipe
    $query = "SELECT tblRecipeIngredients.fkComponentID, tblRecipeIngredients.fldQuantity, tblRecipeIngredients.fkUnitID, tblIngredientUnits.fldUnit, tblIngredients.fldIngredientName, tblIngredients.fkUSDAFoodID 
              FROM tblRecipeIngredients
              JOIN tblIngredients on tblRecipeIngredients.fkComponentID=tblIngredients.pkIngredientID
              JOIN tblIngredientUnits on tblRecipeIngredients.fkUnitID=tblIngredientUnits.pkUnitID
              WHERE tblRecipeIngredients.fldType=1 AND tblRecipeIngredients.fkRecipeID=$RecipeID";
    $result = $mysqli->query($query);

    $nutrients = [];
    $totalWeight = 0;

    // Process each ingredient
    while ($ingredient = $result->fetch_assoc()) {
        $ingredientID = $ingredient['fkComponentID'];
        $quantity = $ingredient['fldQuantity'];
        $unitID = $ingredient['fkUnitID'];
        $USDAFoodID = $ingredient['fkUSDAFoodID'];

        // Convert quantity to grams
        $quantityInGrams = convertToGrams($quantity, $unitID);

        // Query to get nutrient information for the ingredient
        $nutrientQuery = "SELECT tblFoodNutrient.fldAmount, tblFoodNutrient.fldPercentDV, tblNutrients.fldNutrientName, tblNutrients.fldUnit, tblNutrients.fldLabelName
                          FROM tblFoodNutrient
                          JOIN tblNutrients on tblFoodNutrient.fkNutrientID=tblNutrients.pkNutrientID
                          WHERE tblFoodNutrient.fkFoodID=$USDAFoodID
                          ORDER BY tblNutrients.fldLabelRank ASC";
        $nutrientResult = $mysqli->query($nutrientQuery);

        // Process each nutrient
        while ($nutrient = $nutrientResult->fetch_assoc()) {
            $amount = $nutrient['fldAmount'];
            $nutrientName = $nutrient['fldNutrientName'];
            $unit = $nutrient['fldUnit'];
            $labelName = $nutrient['fldLabelName'];

            // Scale the amount based on the quantity in grams
            $scaledAmount = ($amount / 100) * $quantityInGrams;

            // Sum the nutrient content
            if (!isset($nutrients[$labelName])) {
                $nutrients[$labelName] = [
                    'nutrient' => $labelName,
                    'totalAmount' => 0,
                    'rank' => $nutrient['fldLabelRank'],
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
    while ($recipeIngredient = $recipeResult->fetch_assoc()) {
        $componentRecipeID = $recipeIngredient['fkComponentID'];
        $quantity = $recipeIngredient['fldQuantity'];
        $unitID = $recipeIngredient['fkUnitID'];

        // Get the nutrition array from the component recipe
        $nutritionQuery = "SELECT fldNutrition FROM tblRecipes WHERE RecipeID=$componentRecipeID";
        $nutritionResult = $mysqli->query($nutritionQuery);
        $componentNutrition = json_decode($nutritionResult->fetch_assoc()['fldNutrition'], true);

        // Convert the quantity to grams
        $quantityInGrams = convertToGrams($quantity, $unitID);

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
    $response['nutrition']['totalWeight'] = $totalWeight;

    // Add nutrient information to response
    $response['nutrition']['nutrients'] = array_values($nutrients);

    // Update the recipe with the nutrition information
    $nutritionJson = json_encode($response['nutrition']);
    $updateQuery = "UPDATE tblRecipes SET fldNutrition='$nutritionJson' WHERE RecipeID=$RecipeID";
    $mysqli->query($updateQuery);
}

function convertToGrams($quantity, $unitID) {
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
/*
I need a standalone PHP function called NutrientCalculations that has global access to the following variables: $RecipeID, $mysqli, and $response. $mysqli is the database connection, and $response is a JSON array that is returned to Javascript (the header and response are executed outside of this function and do not need to be incorporated into this function)
When triggered, NutrientCalculations should: 
Select all ingredients for each recipe
Select all nutrient information for each ingredient
Appropriately scale the amount of each nutrient (standard values are based on 100g quantities, so they need to be converted based on the amount of the ingredient being used in the recipe)
Sum the nutrient content of all ingredients
Store the composite nutrient content details for the recipe in $response[‘nutrition’]

This will require queries involving tblRecipes, tblRecipeIngredients, tblIngredients, and tblFoodNutrient. The data dictionary for the database is in the USDA repository.
tblRecipeIngredients stores the list of ingredients in a recipe using foreign keys. 
When tblRecipeIngredients.fldType=1, fkComponentID corresponds to tblIngredients.pkIngredientID
When tblRecipeIngredients.fldType=2, fkComponentID corresponds to tblRecipes.RecipeID
(The distinction for fldType=2 is for when another recipe is included in an ingredients list, e.g. a recipe for salad dressing or pizza dough, and fldType=1is for regular ingredients)
So we probably need a couple queries.
The first need is to 
SELECT tblRecipeIngredients.fkComponentID, tblRecipeIngredients.fldQuantity, tblRecipeIngredients.fkUnitID, tblIngredientUnits.fldUnit, tblIngredients.fldIngredientName, tblIngredients.fkUSDAFoodID 
FROM tblRecipeIngredients
JOIN tblIngredients on tblRecipeIngredients.fkComponentID=tblIngredients.pkIngredientID
JOIN tblIngredientUnits on tblRecipeIngredients.fkUnitID=tblIngredientUnits.pkUnitID
WHERE tblRecipeIngredients.fldType=1 

And subsequently, foreach pkIngredientID in that result set, (foreach $ingredients as $ingredient) we need to:
SELECT tblFoodNutrient.fldAmount, tblFoodNutrient.fldPercentDV, tblNutrients.fldNutrientName,tblNutrients.fldUnit, tblNutrients.fldLabelName
FROM tblFoodNutrient
WHERE tblFoodNutrient=fkFoodID=$ingredient[‘fkUSDAFoodID’]
SORT BY tblNutrient.LabelRank ASC
CONVERSIONS
All of the nutrient values in tblFoodNutrient.fldAmount are based on 100g quantities of an ingredient, so we need to convert all other units to grams.
Here are cases for tblIngredients.fkUnitID and the conversion factor to convert to grams. The numeric value is the ID (and what’s in parenthesis is a comment that should be included as a comment in the script, as well as any // comments) 
5 (grams): fldAmount *.01
25 (kilograms): fldAmount*10
6 (ounces):  fldAmount/3.5274
7 (pounds): fldAmount* 4.53592 //Pound is 453.592g

// Non-weight units
If(fkUnitID is not [5,25,6,7]){
//For fluid/volume measurements, convert to mL, and if no direct conversion is available assume 1g/mL
4 (milliliters): fldAmount*.01
11 (liters): fldAmount*10
20 (fluid ounces): fldAmount* 3.33814 //Fluid ounce is 29.5735 mL
10 (cup): fldAmount*2.36588 //cup is 236.588 mL
9 (tablespoon): fldAmount*.15
8 (teaspoon): fldAmount *.05
//For produce, need median piece weights in grams, and weight per cup processed
//FOR OTHER UNITS [Check Conversion Options in tblPortion]
(and what’s in parenthesis is a comment that should be included as a comment in the script, as well as any // comments)
1 (no unit/produce piece): *1
24 (stick): *1
16 (bag) *1.5
17 (box) *4
22 (can) * 2.5
12 (clove) *.05
23 (stalk) * 1
19 (crown) *2
18 (head) * 3
15 (sprig) * .03
14 (bunch) * 2

DO NOT ADD A DEFAULT
}
Once the conversions are complete, we need to sum the fldAmount for each tblNutrients.fldNutrientName AND another array entry for the total weight of all ingredients In grams
//We are calculating the nutritional profile of each individual ingredient in a recipe then calculating the nutrient profile of the entire recipe.
Then we need to format a response in $response[‘nutrition’]. (This function is not responsible for sending the response / formatting a JSON header, etc)

Create an array of all nutrients present in this recipe, with the following information:  
nutrient (tblNutrients.fldLabelName)
Total amount present in this recipe
rank (tblNutrients.fldLabelRank)
unit (tblNutrients.fldNutrientUnit)

And then insert that array into tblRecipes:
UPDATE tblRecipes SET fldNutrition = (the array) WHERE RecipeID=$RecipeID

(And now add a mid-script check/process for fldType=2, which is where another recipe is present in the ingredients list as an ingredient(eg a salad dressing present in a salad recipe). If a recipe has ingredients in tblRecipeIngredients where fldType=2, extract the corresponding nutrition array from tblRecipes with SELECT fldNutrition FROM tblRecipes WHERE RecipeID=fkComponentID.
Convert the quantity of this recipe-as-ingredient to grams, and then add the nutrient subtotals to the array of nutrient information we’ve established with all of the other ingredients (the regular ingredients where fldType=1)
The nutrient content for a certain number of grams will be listed in tblRecipes.fldNutrition, so do the appropriate conversion/division to establish how much nutrients to add for this recipe-as-ingredient.

//Reminders (Include all of these as comments in the script)
//Add fldNutrition to local version
//Incorporate cases from tblFoodPortion
//Show which ingredients are good sources of xyz
//MUST calculate nutrition per 100g and then declare servings in grams
*/
?>