<?php
// Start session management
session_start();

// Include database connection
require_once '../../config/conexion.php';

// Create connection instance
$conexion = new Conexion();
$db = $conexion->getConexion();

// Get current user ID from session
$usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : 'Not set';

echo "<h1>Material System Diagnostics</h1>";
echo "<p>Current user ID: {$usuario_id}</p>";

// Check if tables exist
echo "<h2>Database Tables Check</h2>";
$tables = ['materiales_apoyo', 'asignaciones_material', 'usuarios'];
foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
        $stmt->execute();
        $exists = $stmt->rowCount() > 0;
        echo "<p>Table '{$table}': " . ($exists ? "EXISTS" : "DOES NOT EXIST") . "</p>";
        
        if ($exists) {
            // Show table structure
            $stmt = $db->prepare("DESCRIBE {$table}");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                foreach ($column as $key => $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            
            // Count records
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table}");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "<p>Number of records in '{$table}': {$count}</p>";
            
            // Show sample data
            if ($count > 0) {
                $stmt = $db->prepare("SELECT * FROM {$table} LIMIT 5");
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h3>Sample data from '{$table}':</h3>";
                echo "<table border='1' cellpadding='5'>";
                
                // Table header
                echo "<tr>";
                foreach (array_keys($records[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                // Table data
                foreach ($records as $record) {
                    echo "<tr>";
                    foreach ($record as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error checking table '{$table}': " . $e->getMessage() . "</p>";
    }
}

// Check for materials assigned to current user
echo "<h2>Materials Assigned to Current User</h2>";
if ($usuario_id !== 'Not set') {
    try {
        $stmt = $db->prepare("
            SELECT m.id, m.titulo, m.tipo, a.estudiante_id
            FROM materiales_apoyo m
            JOIN asignaciones_material a ON m.id = a.material_id
            WHERE a.estudiante_id = :usuario_id
        ");
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($materials) > 0) {
            echo "<p>Found " . count($materials) . " materials assigned to user ID {$usuario_id}:</p>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Student ID</th></tr>";
            foreach ($materials as $material) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($material['id']) . "</td>";
                echo "<td>" . htmlspecialchars($material['titulo']) . "</td>";
                echo "<td>" . htmlspecialchars($material['tipo']) . "</td>";
                echo "<td>" . htmlspecialchars($material['estudiante_id']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>No materials found assigned to user ID {$usuario_id}</p>";
            
            // Check if there are any materials at all
            $stmt = $db->prepare("SELECT COUNT(*) FROM materiales_apoyo");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "<p>Total materials in database: {$count}</p>";
            
            if ($count > 0) {
                // Check if there are any assignments
                $stmt = $db->prepare("SELECT COUNT(*) FROM asignaciones_material");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo "<p>Total material assignments in database: {$count}</p>";
                
                if ($count > 0) {
                    // Show all assignments
                    $stmt = $db->prepare("
                        SELECT a.material_id, a.estudiante_id, m.titulo, m.tipo
                        FROM asignaciones_material a
                        JOIN materiales_apoyo m ON a.material_id = m.id
                        LIMIT 10
                    ");
                    $stmt->execute();
                    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<h3>Sample material assignments:</h3>";
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>Material ID</th><th>Student ID</th><th>Title</th><th>Type</th></tr>";
                    foreach ($assignments as $assignment) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($assignment['material_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($assignment['estudiante_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($assignment['titulo']) . "</td>";
                        echo "<td>" . htmlspecialchars($assignment['tipo']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error checking materials: " . $e->getMessage() . "</p>";
    }
}

// Add a form to manually insert a test material
echo "<h2>Insert Test Material</h2>";
echo "<form method='post' action=''>";
echo "<input type='hidden' name='action' value='insert_test'>";
echo "<p><label>Title: <input type='text' name='title' value='Test Material'></label></p>";
echo "<p><label>Description: <input type='text' name='description' value='This is a test material'></label></p>";
echo "<p><label>Type: 
        <select name='tipo'>
            <option value='videos'>Videos</option>
            <option value='documentacion'>Documentación</option>
            <option value='articulos'>Artículos</option>
            <option value='herramientas'>Herramientas</option>
            <option value='motivacion'>Motivación</option>
        </select>
      </label></p>";
echo "<p><label>Link: <input type='text' name='enlace' value='https://example.com'></label></p>";
echo "<p><label>Student ID: <input type='text' name='estudiante_id' value='{$usuario_id}'></label></p>";
echo "<p><input type='submit' value='Insert Test Material'></p>";
echo "</form>";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_test') {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Insert material
        $stmt = $db->prepare("
            INSERT INTO materiales_apoyo (
                titulo, 
                descripcion, 
                tipo, 
                enlace
            ) VALUES (
                :titulo, 
                :descripcion, 
                :tipo, 
                :enlace
            )
        ");
        
        $stmt->bindParam(':titulo', $_POST['title']);
        $stmt->bindParam(':descripcion', $_POST['description']);
        $stmt->bindParam(':tipo', $_POST['tipo']);
        $stmt->bindParam(':enlace', $_POST['enlace']);
        $stmt->execute();
        
        $material_id = $db->lastInsertId();
        
        // Insert assignment
        $stmt = $db->prepare("
            INSERT INTO asignaciones_material (
                material_id, 
                estudiante_id
            ) VALUES (
                :material_id, 
                :estudiante_id
            )
        ");
        
        $stmt->bindParam(':material_id', $material_id);
        $stmt->bindParam(':estudiante_id', $_POST['estudiante_id']);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        echo "<p style='color: green;'>Test material inserted successfully with ID {$material_id} and assigned to student ID {$_POST['estudiante_id']}</p>";
    } catch (PDOException $e) {
        // Rollback transaction
        $db->rollBack();
        echo "<p style='color: red;'>Error inserting test material: " . $e->getMessage() . "</p>";
    }
}

// Add a form to fix student assignments
echo "<h2>Fix Student Assignments</h2>";
echo "<form method='post' action=''>";
echo "<input type='hidden' name='action' value='fix_assignments'>";
echo "<p><label>Current User ID: <input type='text' name='usuario_id' value='{$usuario_id}'></label></p>";
echo "<p><input type='submit' value='Assign All Materials to Current User'></p>";
echo "</form>";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix_assignments') {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get all materials
        $stmt = $db->prepare("SELECT id FROM materiales_apoyo");
        $stmt->execute();
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assigned_count = 0;
        
        foreach ($materials as $material) {
            // Check if already assigned
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM asignaciones_material 
                WHERE material_id = :material_id AND estudiante_id = :estudiante_id
            ");
            $stmt->bindParam(':material_id', $material['id']);
            $stmt->bindParam(':estudiante_id', $_POST['usuario_id']);
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;
            
            if (!$exists) {
                // Insert assignment
                $stmt = $db->prepare("
                    INSERT INTO asignaciones_material (
                        material_id, 
                        estudiante_id
                    ) VALUES (
                        :material_id, 
                        :estudiante_id
                    )
                ");
                
                $stmt->bindParam(':material_id', $material['id']);
                $stmt->bindParam(':estudiante_id', $_POST['usuario_id']);
                $stmt->execute();
                
                $assigned_count++;
            }
        }
        
        // Commit transaction
        $db->commit();
        
        echo "<p style='color: green;'>Successfully assigned {$assigned_count} materials to user ID {$_POST['usuario_id']}</p>";
    } catch (PDOException $e) {
        // Rollback transaction
        $db->rollBack();
        echo "<p style='color: red;'>Error fixing assignments: " . $e->getMessage() . "</p>";
    }
}

// Add a link to material_apoyo.php
echo "<p><a href='material_apoyo.php' target='_blank'>Go to Material de Apoyo</a></p>";
?>
