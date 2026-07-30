$outputPath = 'C:\Users\Usman Computers\Downloads\Cafe9_Menu.xlsx'

$sections = @(
    @{
        Name = 'HOT COFFEE'
        Headers = @('Rate')
        Rows = @(
            @('Cappuccino', 499),
            @('Cafe Latte', 499),
            @('Irish Latte', 599),
            @('Hazelnut Latte', 599),
            @('Caramel Latte', 599),
            @('Mocaccino', 230),
            @('Americano', 230),
            @('Espresso', 230),
            @('Extra Shot', 230),
            @('Macchiato', 230),
            @('Hot Chocolate', 499),
            @('Plain Green Tea', 99),
            @('Desi Green Tea', 160),
            @('Mix Tea / Separate Tea', 170),
            @('Gur Tea / Karak Tea', 199),
            @('Cardamom Tea', 150)
        )
    },
    @{
        Name = 'COLD COFFEE'
        Headers = @('Rate')
        Rows = @(
            @('Cold Coffee', 499),
            @('Caramel Cold Coffee', 599),
            @('Vanilla Cold Coffee', 599),
            @('Hazelnut Cold Coffee', 599),
            @('Irish Cold Coffee', 599)
        )
    },
    @{
        Name = 'SMOOTHIE'
        Headers = @('Rate')
        Rows = @(
            @('Strawberry Smoothie', 699),
            @('Wild Berry', 699)
        )
    },
    @{
        Name = 'ICE CREAM'
        Headers = @('Rate')
        Rows = @(
            @('Ice Cream Per Scope', 120),
            @('Topping', 50)
        )
    },
    @{
        Name = 'ICE SHAKE'
        Headers = @('Rate')
        Rows = @(
            @('Oreo Ice Shake', 499),
            @('Vanilla Ice Shake', 499),
            @('Caramel Ice Shake', 550),
            @('Nutella Ice Shake', 699),
            @('Chocolate Ice Shake', 499)
        )
    },
    @{
        Name = 'CHILLERS'
        Headers = @('Rate')
        Rows = @(
            @('Tropical Chiller', 499),
            @('Kiwi Chiller', 499),
            @('Strawberry Chiller', 499),
            @('Raspberry Chiller', 499),
            @('Blue Berry Chiller', 499),
            @('Pineapple Chiller', 499),
            @('Green Apple Chiller', 499),
            @('Lime Chiller', 499),
            @('Peach Chiller', 499),
            @('Mix Chiller', 499),
            @('Coconut Chiller', 499)
        )
    },
    @{
        Name = 'DESSERTS'
        Headers = @('Rate')
        Rows = @(
            @('Red Velvet Pastry', 200),
            @('Snicker Pastry', 300),
            @('KitKat Pastry', 300),
            @('Chocolate Pastry', 200),
            @('Pineapple Pastry', 200),
            @('Nutella Pastry', 300),
            @('Lotus Pastry', 300),
            @('Cheese Cake Slice', 350),
            @('Three Milk Cake', 350),
            @('Lotus Three Milk', 399),
            @('Pistachio Three Milk', 399),
            @('Galaxy / Nutella', 350),
            @('Molten Lava', 699),
            @('Plain Brownie', 200),
            @('Walnut Brownie', 230),
            @('Cashewnut Brownie', 230),
            @('Lemon Tart', 210),
            @('Walnut Tart', 300),
            @('Cookie', 199),
            @('Donut', 150),
            @('Cup Cake', 199),
            @('Basbousa', 300),
            @('Chocolate Ball', 120)
        )
    },
    @{
        Name = 'SNACKS'
        Headers = @('Rate')
        Rows = @(
            @('Fries', 275),
            @('Family Fries', 399),
            @('Fried Wings (6 Piece)', 560),
            @('Fish N Chips', 949),
            @('Chicken Patties', 150),
            @('Gymkhana Special Grilled Sandwich', 525),
            @('Club Sandwich', 499),
            @('Parmesan Chicken', 1449),
            @('Stuffed Chicken With Rice', 1350),
            @('Chicken Steak with Rice', 1149),
            @('Beef Steak with Rice', 1550),
            @('Grilled Fish with Rice', 1199),
            @('Malai Boti (4 Piece) With Rice', 499),
            @('Chicken Hariyali Boti (4 Piece) With Rice', 499),
            @('Chest Piece With Rice', 490),
            @('Leg Piece with Rice', 450),
            @('Pizza (Medium)', 899),
            @('Burger', 625)
        )
    },
    @{
        Name = 'BAKERY'
        Headers = @('Rate')
        Rows = @(
            @('Fruit Cake', 430),
            @('Mix Biscuit', 120),
            @('Brown Bread', 130),
            @('White Bread', 60)
        )
    },
    @{
        Name = 'DRINKS'
        Headers = @('Rate')
        Rows = @(
            @('Red Bull', 299),
            @('Soft Drink', 275),
            @('Fresh Lime', 220),
            @('Mineral Water Small', 220)
        )
    }
)

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

try {
    $workbook = $excel.Workbooks.Add()
    $sheet = $workbook.Worksheets.Item(1)
    $sheet.Name = 'Cafe9 Menu'

    $row = 1
    $sheet.Cells.Item($row, 1) = 'CAFE 9 MENU'
    $sheet.Range("A$row:C$row").Merge() | Out-Null
    $sheet.Range("A$row:C$row").Font.Bold = $true
    $sheet.Range("A$row:C$row").Font.Size = 18
    $sheet.Range("A$row:C$row").HorizontalAlignment = -4108
    $row++

    $sheet.Cells.Item($row, 1) = 'Gujrat Gymkhana'
    $sheet.Cells.Item($row, 2) = 'Bhimber Road, Gujrat'
    $sheet.Cells.Item($row, 3) = '15-Oct-2024'
    $sheet.Range("A$row:C$row").Font.Italic = $true
    $row += 2

    foreach ($section in $sections) {
        $sheet.Cells.Item($row, 1) = $section.Name
        $sheet.Range("A$row:C$row").Merge() | Out-Null
        $sheet.Range("A$row:C$row").Font.Bold = $true
        $sheet.Range("A$row:C$row").Font.Size = 14
        $sheet.Range("A$row:C$row").Interior.Color = 10092543
        $sheet.Range("A$row:C$row").HorizontalAlignment = -4108
        $row++

        $sheet.Cells.Item($row, 1) = 'S.No'
        $sheet.Cells.Item($row, 2) = 'Item Name'
        $sheet.Cells.Item($row, 3) = $section.Headers[0]
        $sheet.Range("A$row:C$row").Font.Bold = $true
        $sheet.Range("A$row:C$row").Interior.Color = 13431551
        $sheet.Range("A$row:C$row").HorizontalAlignment = -4108
        $headerRow = $row
        $row++

        $serial = 1
        foreach ($item in $section.Rows) {
            $sheet.Cells.Item($row, 1) = $serial
            $sheet.Cells.Item($row, 2) = $item[0]
            $sheet.Cells.Item($row, 3) = $item[1]
            $row++
            $serial++
        }

        $sheet.Range("A$headerRow:C$($row - 1)").Borders.LineStyle = 1
        $row++
    }

    $sheet.Columns.Item('A').ColumnWidth = 8
    $sheet.Columns.Item('B').ColumnWidth = 45
    $sheet.Columns.Item('C').ColumnWidth = 14
    $sheet.Columns.Item('A:C').VerticalAlignment = -4108

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
