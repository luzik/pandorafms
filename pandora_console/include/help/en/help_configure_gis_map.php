<?php
/**
 * @package Include/help/en
 */
?>
<h1>GIS Map Configuration</h1>

<p>
This page is the place to configure a GIS Map.
</p>
<h2>Map Name</h2>
<p>
Each map has a desciptive name used to recognice the map within Pandora FMS.
</p>
<h2>Select Connections</h2>
<p>
The first step is to select the main </strong>connection</strong> used in this GIS Map. At least one connection must be selected to configure the GIS Map, but it's possible to add more by pressing the <img src="../images/add.png" alt="Add" /> (Add) icon.
</p>
<p>
When the firs connection is set, Pandora FMS asks if you want to use the default values of the connection for the map, to avoid to write again all the information. Also if the default connection for the map is changed (using the radio button), Pandora FMS will ask again if you want to use the values of the new default connection.
</p>
<h2>Map Paramteres</h2>
<p>
Once the selection of the connection (or connections) is done, there is a posibility of changing the parameters that were set for the connection and personalize this map. It is possble to set the <strong>center</strong> of the map (the place that will apear when the map is open), the <strong>default zoom</strong> level (the level of zoom to set when the map is open), and the <strong>default position</strong> (the place to put the agents that does not have positional information).
</p>
<p>
<strong>Options:</strong>
</p>
<div>
<dl>
<dt>Map name</dt>
<dd>Set the <strong>Map Name</strong>. Use sort and descriptive names</dd>
<dt>Group</dt>
<dd>Set the <strong>Group</strong> that owns the map for ACL purposes</dd>
<dt>Default Zoom</dt>
<dd>Set the <strong>Default zoom</strong> for the map, when the map is open this is the level of zoom that is set...</dd>
<dt>Center Longitude</dt>
<dt>Center Latitude</dt>
<dt>Center Altitude</dt>
<dd>Set the <strong>Longitude</strong>, <strong>Latitude</strong> and <strong>Altitude</strong> for the <strong>center</strong> of the map, when the map is open this is the center of the view </dd>
<dt>Default Longitude</dt>
<dt>Default Latitude</dt>
<dt>Default Altitude</dt>
<dd>Set the <strong>Longitude</strong>, <strong>Latitude</strong> and <strong>Altitude</strong> for the <strong>default position</strong> of the map, this is the place where all the agents <strong>without</strong> positional information are placed</dd>
</dl>
</div>
<h2>Layer setup</h2>
<p>
Each map has one or more layers<sup><span style="font-size:75%">1</span></sup> to show the agents. Each layer can have show the agents of a <strong>group</strong> and/or a <strong>list of agents</strong>. This way it's easy to set up the agents that will be shown on each Layer.
</p>
<hr/>
<sup><span style="font-size:75%;">1</span></sup> <span style="font-size:85%;">The default map can have 0 layers as is the one used in the agent GIS view and only uses one layer with the agent name.</span>

