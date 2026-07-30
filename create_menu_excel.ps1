$outputPath = 'C:\Users\Usman Computers\Downloads\Gujrat_Gymkhana_Menu.xlsx'

$sections = @(
    @{
        Name = 'STARTERS'
        Headers = @('Rate')
        Rows = @(
            @('Prawn Tempura (6 Pcs)', 2099),
            @('BBQ Honey Wings (6 Pcs)', 699),
            @('Finger Fish (6 Pcs)', 1149),
            @('Fish N Chips (2 Pcs)', 1199),
            @('Crispy Chicken Strips (4 Pcs)', 700),
            @('Garlic Bread (6 Pcs)', 249),
            @('Fries', 349),
            @('Fish Crackers', 199)
        )
    },
    @{
        Name = 'SOUP'
        Headers = @('Half', 'Full')
        Rows = @(
            @('Gymkhana Special Soup', 499, 1999),
            @('Chicken Hot and Sour Soup', 349, 1399),
            @('Chicken Corn Soup', 225, 899),
            @('19-B Soup', 449, 1799)
        )
    },
    @{
        Name = 'SALAD'
        Headers = @('Rate')
        Rows = @(
            @('Broccoli Salad', 1049),
            @('Caesar Salad', 899),
            @('Fresh Salad', 149),
            @('Kachumar Salad', 199),
            @('Russian Salad', 599),
            @('Greek Salad', 899),
            @('Raita', 199),
            @('Mint Sauce', 199)
        )
    },
    @{
        Name = 'CONTINENTAL'
        Headers = @('Rate')
        Rows = @(
            @('Parmesan Chicken', 1449),
            @('Stuffed Chicken Breast', 1399)
        )
    },
    @{
        Name = 'CHICKEN STEAK'
        Headers = @('Rate')
        Rows = @(
            @('Jalapeno Steak', 1199),
            @('Moroccan Steak', 1199),
            @('Tarragon Steak', 1199),
            @('Ginger Pepper Steak', 1199),
            @('Black Pepper Steak', 1199),
            @('Mexican Steak', 1199)
        )
    },
    @{
        Name = 'BEEF STEAK'
        Headers = @('Rate')
        Rows = @(
            @('Jalapeno Steak', 1699),
            @('Moroccan Steak', 1699),
            @('Tarragon Steak', 1699),
            @('Ginger Pepper Steak', 1699),
            @('Black Pepper Steak', 1699),
            @('Mexican Steak', 1699)
        )
    },
    @{
        Name = 'DEEP PAN / THIN CRUST PIZZA'
        Headers = @('Medium', 'Large')
        Rows = @(
            @('Gymkhana Special', 1049, 1699),
            @('Kebab/Chicken Stuffed Pizza', 1049, 1699),
            @('Chicken Tikka', 1049, 1699),
            @('Chicken Fajita', 1049, 1699),
            @('Cheese Pizza', 999, 1499),
            @('Extra Topping Charges', 0, 250)
        )
    },
    @{
        Name = 'BURGER'
        Headers = @('Rate')
        Rows = @(
            @('Mighty Zinger Burger', 625),
            @('Chicken Patty Cheese Burger', 525),
            @('Beef Patty Burgur', 649),
            @('Grilled Chicken Burger', 625)
        )
    },
    @{
        Name = 'SANDWICHES'
        Headers = @('Rate')
        Rows = @(
            @('Gymkhana Special Grilled Sandwich', 525),
            @('Club Sandwich', 699),
            @('Bar-B-Que Sandwich', 649),
            @('Jalapeno Sandwich', 499)
        )
    },
    @{
        Name = 'PASTA'
        Headers = @('Rate')
        Rows = @(
            @('Spaghetti in Tomato Sauce', 449),
            @('Spicy Red Sauce Pasta', 499),
            @('Creamy Pasta', 949),
            @('Alfredo Pasta', 999),
            @('Chicken Spaghetti', 499),
            @('Baked Chicken Pasta', 1249)
        )
    },
    @{
        Name = 'RICE FAMILY'
        Headers = @('Rate')
        Rows = @(
            @('Gymkhana Special Rice', 1249),
            @('Vegetable Fried Rice', 649),
            @('Egg Fried Rice', 899),
            @('Chicken Fried Rice', 999),
            @('Chicken Masala Rice', 999),
            @('Steamed Rice', 299),
            @('Garlic Fried Rice', 649)
        )
    },
    @{
        Name = 'CHINESE GRAVY FAMILY'
        Headers = @('Chicken', 'Beef')
        Rows = @(
            @('Chicken Garlic/Beef', 1649, 1949),
            @('Chicken/Beef Manchurian', 1649, 1949),
            @('Kung Pao Chicken/Beef', 1399, 1799),
            @('Chicken/Beef Shashlik', 1649, 1949),
            @('Sweet and Sour Chicken/Beef', 1399, 1799),
            @('Chicken/Beef Cashew Nut', 1699, 1999),
            @('Chicken/Beef Chili Dry', 1449, 1899),
            @('Chicken Schezwan', 1399, 1899)
        )
    },
    @{
        Name = 'CHINESE GRAVY SINGLE'
        Headers = @('Chicken', 'Beef')
        Rows = @(
            @('Chicken Garlic/Beef', 749, 1199),
            @('Chicken/Beef Manchurian', 799, 1249),
            @('Kung Pao Chicken/Beef', 799, 1249),
            @('Chicken/Beef Shashlik', 799, 1249),
            @('Sweet and Sour Chicken/Beef', 799, 1249),
            @('Chicken/Beef Cashew Nut', 999, 1549),
            @('Chicken/Beef Chili Dry', 799, 1549),
            @('Chicken/Beef Schezwan', 749, 1499)
        )
    },
    @{
        Name = 'CHOWMEIN'
        Headers = @('Half', 'Full')
        Rows = @(
            @('Gymkhana Special Chowmein', 510, 1020),
            @('Chicken Chowmein', 490, 950),
            @('Vegetable Chowmein', 450, 750)
        )
    },
    @{
        Name = 'BAR B QUE'
        Headers = @('Rate')
        Rows = @(
            @('Chicken Malai Boti (8 Pcs)', 849),
            @('Chicken Tikka (Leg)', 399),
            @('Chicken Tikka (Chest)', 450),
            @('Chicken Tikka Boti Boneless (8 Pcs)', 799),
            @('Chicken Haryali Boti (8 Pcs)', 799),
            @('Shinwari Lamb Chaamp (1/2 Kg)', 1999),
            @('Shinwari Lamb Chaamp (1 Kg)', 3999)
        )
    },
    @{
        Name = 'PLATTERS'
        Headers = @('Rate')
        Rows = @(
            @('BBQ Platter (Single)', 2249),
            @('Special Family BBQ Mix Platter', 5699)
        )
    },
    @{
        Name = 'KEBAB'
        Headers = @('2 Pcs', '4 Pcs')
        Rows = @(
            @('Mutton Kebab', 699, 1399),
            @('Chicken Kebab', 349, 699),
            @('Chicken Reshmi Kebab', 399, 749),
            @('Chicken Cheese Kebab', 449, 899)
        )
    },
    @{
        Name = 'SEA FOOD'
        Headers = @('Rate')
        Rows = @(
            @('Gymkhana Special Fish Fillet (1 Pc)', 1749),
            @('Grilled Jumbo Prawn (6 Pcs)', 1999),
            @('Fish Tikka (4 Pcs)', 749),
            @('Grilled Fish', 1299)
        )
    },
    @{
        Name = 'CHICKEN KARAHI'
        Headers = @('Half', 'Full')
        Rows = @(
            @('Chicken Karahi', 949, 1649),
            @('Chicken Black Pepper Karahi', 949, 1649),
            @('Chicken White Karahi', 949, 1649),
            @('Chicken Desi Murgh Karahi', '', 2999),
            @('Chicken Achari Karahi', 949, 1649)
        )
    },
    @{
        Name = 'CHICKEN HANDI'
        Headers = @('Half', 'Full')
        Rows = @(
            @('Chicken Handi Boneless', 1049, 1799),
            @('Chicken White Handi', 1049, 1799),
            @('Kebab Masala', 899, 1599),
            @('Kofta Masala', 899, 1599),
            @('Chicken Achari', 1049, 1799),
            @('Smoke BBQ Handi', 1099, 1849),
            @('Chicken Madrasi Handi', 1049, 1799),
            @('Chicken Ginger', 1049, 1799),
            @('Chicken Jalfrezi', 1099, 1849)
        )
    },
    @{
        Name = 'MUTTON'
        Headers = @('Half', 'Full')
        Rows = @(
            @('Mutton Karahi', 1899, 3599),
            @('Mutton White Karahi', 1899, 3599),
            @('Mutton Black Paper', 1899, 3599),
            @('Mutton Achari', 1899, 3599),
            @('Shinwari Namkeen Mutton', 1949, 3799),
            @('Tawa Qeema', 1799, 2999),
            @('Alu Gosht', 1899, 3599)
        )
    },
    @{
        Name = 'DAAL & VEGETABLE'
        Headers = @('Rate')
        Rows = @(
            @('Daal Maash', 599),
            @('Daal Chana', 499),
            @('Anda Piyaz', 325),
            @('Mix Vegetable', 349)
        )
    },
    @{
        Name = 'SWEETS'
        Headers = @('Rate')
        Rows = @(
            @('Shahi Kheer', 275),
            @('Firni', 299),
            @('Fruit Trifle', 325),
            @('Gulab Jaman', 250),
            @('Ras Malai', 275),
            @('Bread Pudding', 375),
            @('Molten Lava with Ice Cream', 899)
        )
    },
    @{
        Name = 'TANDOOR'
        Headers = @('Rate')
        Rows = @(
            @('Roti (Per Head)', 80),
            @('SP Roti', 25),
            @('Sada Naan', 50),
            @('Roghni Naan', 80),
            @('Garlic Naan', 99),
            @('Kalwanji Naan', 99),
            @('Cheese Naan', 549),
            @('Tandoori Paratha', 99),
            @('Alu Naan', 199),
            @('Chicken Qeema Naan', 499),
            @('Mutton Qeema Naan', 599)
        )
    },
    @{
        Name = 'DRINKS'
        Headers = @('Rate')
        Rows = @(
            @('Mineral Water Small', 65),
            @('Mineral Water Large', 110),
            @('Soft Drink', 130),
            @('Fresh Lime', 150)
        )
    }
)

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

try {
    $workbook = $excel.Workbooks.Add()
    $sheet = $workbook.Worksheets.Item(1)
    $sheet.Name = 'Menu'

    $row = 1
    $sheet.Cells.Item($row, 1) = 'GUJRAT GYMKHANA MENU'
    $sheet.Range("A$row:D$row").Merge() | Out-Null
    $sheet.Range("A$row:D$row").Font.Bold = $true
    $sheet.Range("A$row:D$row").Font.Size = 18
    $sheet.Range("A$row:D$row").HorizontalAlignment = -4108
    $row++

    $sheet.Cells.Item($row, 1) = 'Opening Hours'
    $sheet.Cells.Item($row, 2) = 'Monday - Sunday'
    $sheet.Cells.Item($row, 3) = '12:00 PM - 11:30 PM'
    $sheet.Range("A$row:C$row").Font.Italic = $true
    $row += 2

    foreach ($section in $sections) {
        $sheet.Cells.Item($row, 1) = $section.Name
        $sheet.Range("A$row:D$row").Merge() | Out-Null
        $sheet.Range("A$row:D$row").Font.Bold = $true
        $sheet.Range("A$row:D$row").Font.Size = 14
        $sheet.Range("A$row:D$row").Interior.Color = 10092543
        $sheet.Range("A$row:D$row").HorizontalAlignment = -4108
        $row++

        $sheet.Cells.Item($row, 1) = 'S.No'
        $sheet.Cells.Item($row, 2) = 'Item Name'
        $sheet.Cells.Item($row, 3) = $section.Headers[0]
        if ($section.Headers.Count -gt 1) {
            $sheet.Cells.Item($row, 4) = $section.Headers[1]
        }
        else {
            $sheet.Cells.Item($row, 4) = ''
        }

        $sheet.Range("A$row:D$row").Font.Bold = $true
        $sheet.Range("A$row:D$row").Interior.Color = 13431551
        $sheet.Range("A$row:D$row").HorizontalAlignment = -4108
        $headerRow = $row
        $row++

        $serial = 1
        foreach ($item in $section.Rows) {
            $sheet.Cells.Item($row, 1) = $serial
            $sheet.Cells.Item($row, 2) = $item[0]
            $sheet.Cells.Item($row, 3) = $item[1]
            if ($item.Count -gt 2) {
                $sheet.Cells.Item($row, 4) = $item[2]
            }
            $row++
            $serial++
        }

        $sheet.Range("A$headerRow:D$($row - 1)").Borders.LineStyle = 1
        $row++
    }

    $sheet.Columns.Item('A').ColumnWidth = 8
    $sheet.Columns.Item('B').ColumnWidth = 42
    $sheet.Columns.Item('C').ColumnWidth = 14
    $sheet.Columns.Item('D').ColumnWidth = 14
    $sheet.Columns.Item('A:D').VerticalAlignment = -4108
    $sheet.Columns.Item('A:D').WrapText = $true

    $usedRange = $sheet.UsedRange
    $usedRange.Rows.AutoFit() | Out-Null

    $sheet.Range('A1:D3').Borders.LineStyle = 1

    $xlOpenXMLWorkbook = 51
    $workbook.SaveAs($outputPath, $xlOpenXMLWorkbook)
    $workbook.Close($true)
    Write-Output $outputPath
}
finally {
    $excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($sheet) | Out-Null
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($workbook) | Out-Null
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
